<?php

/**
 * Endpoints de autenticação da API.
 * Responsável por login, registro e
 * gerenciamento de tokens JWT.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends Controller {
    /**
     * Login e criação de token JWT
     */
    public function login(Request $request) {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Credenciais inválidas'], 401);
        }

        // ✅ BUSCA O USUÁRIO LOGADO
        $user = auth()->user();

        // ✅ CARREGA AS PERMISSÕES
        $user->load('roles', 'permissions'); // Opcional carregar roles se precisar
        $permissions = $user->getAllPermissions()->pluck('name');

        $ttl = config('jwt.ttl'); // Pega o TTL (pode ser null)

        // Prepara a resposta base
        $responseData = [
            'access_token' => $token,
            'token_type' => 'bearer',
            // ✅ INCLUI OS DADOS DO USUÁRIO
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $permissions, // 👈 ENVIA AS PERMISSÕES
            ]
        ];
        // Só adiciona 'expires_in' se ele tiver um valor (não for null)
        if ($ttl !== null) { // Checa explicitamente por null
            $responseData['expires_in'] = $ttl * 60; // Converte minutos para segundos
        }
        // RETORNA TUDO
        return response()->json($responseData);
    }

    /**
     * Registro de novo usuário
     */
    public function register(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $roleUser = Role::firstOrCreate(['name' => 'user']);
        $user->assignRole($roleUser);

        // JWT login automático após registro
        $token = auth('api')->login($user);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Logout (revoga token JWT atual)
     */
    public function logout() {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Logout realizado com sucesso']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Não foi possível deslogar'], 500);
        }
    }

    /**
     * Informações do usuário autenticado
     */
    public function me(Request $request) {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // ✅ CARREGA AS PERMISSÕES ANTES DE ENVIAR
            $user->load('roles', 'permissions');
            $permissions = $user->getAllPermissions()->pluck('name');

            // Retorna o usuário com a lista de permissões
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $permissions, // 👈 ANEXA AS PERMISSÕES
            ]);

        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expirado'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token inválido'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token não fornecido'], 401);
        }
    }

    /**
     * Renovar token JWT
     */
    public function refresh() {
        try {
            $newToken = JWTAuth::refresh();
            $ttl = config('jwt.ttl'); // Pega o TTL (pode ser null)

            // Prepara a resposta base
            $responseData = [
                'access_token' => $newToken,
                'token_type' => 'bearer',
            ];

            // Só adiciona 'expires_in' se ele tiver um valor (não for null)
            if ($ttl) {
                $responseData['expires_in'] = $ttl * 60; // Converte minutos para segundos
            }

            return response()->json($responseData);

        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expirou, faça login novamente'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token inválido'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Não foi possível renovar o token'], 401);
        }
    }
}
