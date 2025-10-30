<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// 👈 1. IMPORTAR O REGISTRAR

class ChatAutoLoginController extends Controller {
    public function autoLogin(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'account_id' => 'required|string'
        ]);

        $email = $request->string('email');
        $accountId = $request->string('account_id');

        if (Str::startsWith($email, '{{') || Str::startsWith($accountId, '{{')) {
            Log::warning('ChatAutoLogin: Recebido com parâmetros de placeholder.', ['email' => $email, 'account_id' => $accountId]);
            return response()->json(['success' => false, 'message' => 'Parâmetros de substituição inválidos.'], 400);
        }

        try {
            Log::info("ChatAutoLogin: Iniciando para email: " . $email);

            // Cria ou encontra o usuário
            $user = User::firstOrCreate(
                ['email' => $email], // Condições para ENCONTRAR
                [                   // Dados para CRIAR
                    'name' => (string)$email,
                    'password' => Hash::make(Str::random(16)),
                    'account_id' => (string)$accountId
                ]
            );

            // 👈 2. LÓGICA DE ATRIBUIÇÃO DE PAPEL À PROVA DE FALHAS
            $guard = 'api';
            $userRoleName = 'user'; // O papel que queremos atribuir

            // Se o usuário foi recém-criado OU não tem nenhum papel 'api', atribui o papel 'user'
            if ($user->wasRecentlyCreated || $user->roles()->where('guard_name', $guard)->count() === 0) {

                if ($user->wasRecentlyCreated) {
                    Log::info("ChatAutoLogin: Usuário recém-criado (ID: {$user->id}). Atribuindo papel...");
                } else {
                    Log::warning("ChatAutoLogin: Usuário existente (ID: {$user->id}) não tem papel 'api'. Atribuindo 'user'...");
                }

                // 👇 A "MÁGICA" PARA EVITAR CACHE 👇
                // Limpa o cache do Spatie ANTES de tentar buscar o papel
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                $userRole = Role::findByName($userRoleName, $guard); // Busca o papel 'user' do guard 'api'

                if ($userRole) {
                    $user->assignRole($userRole); // Atribui o papel
                    Log::info("ChatAutoLogin: Papel '{$userRoleName}' (guard 'api') atribuído com sucesso.");
                } else {
                    Log::error("ChatAutoLogin: FALHA AO ATRIBUIR PAPEL - Papel '{$userRoleName}' (guard 'api') não encontrado no DB.");
                }
            } else {
                Log::info("ChatAutoLogin: Usuário existente (ID: {$user->id}) já tem papéis.", ['roles' => $user->getRoleNames()]);
            }
            // 👆 FIM DA ATRIBUIÇÃO DE PAPEL

            $token = JWTAuth::fromUser($user);
            Log::info("ChatAutoLogin: Token gerado.");

            // Lógica da Sala (updateOrCreate)
            $expectedSlug = 'sala-' . Str::slug((string)$accountId);
            $expectedName = 'Espaço #' . (string)$accountId;
            $room = Room::updateOrCreate(
                ['slug' => $expectedSlug],
                [
                    'name' => $expectedName,
                    'description' => 'Sala automática para account_id ' . (string)$accountId,
                    'is_private' => true,
                    'created_by' => $user->id,
                ]
            );

            Log::info("ChatAutoLogin: Sala processada. Vinculando usuário...");
            $room->ensureUserMembership($user->id);
            Log::info("ChatAutoLogin: Usuário vinculado.");

            // Carrega permissões (agora o usuário terá!)
            $user->forgetCachedPermissions(); // Limpa o cache DE NOVO para ler as permissões recém-atribuídas
            $permissions = $user->getAllPermissions()->pluck('name');
            Log::info("ChatAutoLogin: Permissões carregadas para resposta:", $permissions->toArray());

            // Monta a resposta
            $responseData = [
                'success' => true,
                'message' => 'Auto-login realizado com sucesso.',
                'token' => $token,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'account_id' => $user->account_id,
                        'permissions' => $permissions, // Agora terá as permissões de 'user'
                    ],
                    'room' => [
                        'id' => $room->id,
                        'slug' => $room->slug,
                        'name' => $room->name,
                        'description' => $room->description,
                        'is_private' => $room->is_private,
                    ],
                ],
            ];

            Log::info("ChatAutoLogin: Dados FINAIS a serem retornados como JSON:", $responseData);
            return response()->json($responseData);

        } catch (Exception $e) {
            Log::error('Erro CRÍTICO no ChatAutoLoginController@autoLogin: ' . $e->getMessage(), [
                'email' => $email, 'accountId' => $accountId, 'exception_trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 'message' => 'Erro interno no servidor durante o auto-login.', 'error' => $e->getMessage()
            ], 500);
        }
    }
}
