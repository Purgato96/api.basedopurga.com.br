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

class ChatAutoLoginController extends Controller
{
    /**
     * Auto login para integração com ChatRace
     * POST /api/v1/auth/auto-login
     */
    public function autoLogin(Request $request)
    {
        // Validação completa
        $request->validate([
            'email' => 'required|email',
            'account_id' => 'required|string'
        ]);

        $email = $request->string('email');
        $accountId = $request->string('account_id');

        // Verificação de placeholders
        if (Str::startsWith($email, '{{') || Str::startsWith($accountId, '{{')) {
            Log::warning('ChatAutoLogin: Recebido com parâmetros de placeholder não substituídos.', ['email' => $email, 'account_id' => $accountId]);
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros de substituição inválidos.'
            ], 400);
        }

        try {
            Log::info("ChatAutoLogin: Iniciando para email: " . $email);

            // --- CORREÇÃO 1: Sintaxe completa do firstOrCreate ---
            $user = User::firstOrCreate(
                ['email' => $email], // Condições para ENCONTRAR
                [                   // Dados para CRIAR se não encontrar
                    'name'       => (string)$email, // Nome padrão (pode ser atualizado depois)
                    'password'   => Hash::make(Str::random(16)), // Senha aleatória segura
                    'account_id' => (string)$accountId
                ]
            );

            if (!$user instanceof \App\Models\User) {
                Log::critical("ChatAutoLogin: ERRO GRAVE - User::firstOrCreate não retornou um objeto User!", ['result' => gettype($user)]);
                throw new Exception("Falha ao obter objeto do usuário.");
            }
            Log::info("ChatAutoLogin: Usuário encontrado/criado com ID: " . $user->id);

            // Gera o token diretamente
            $token = JWTAuth::fromUser($user);
            Log::info("ChatAutoLogin: Token gerado com sucesso.");

            // --- LÓGICA DA SALA CORRIGIDA ---
            $expectedSlug = 'sala-' . Str::slug((string)$accountId);
            $expectedName = 'Espaço #' . (string)$accountId;
            Log::info("ChatAutoLogin: Procurando/Criando sala com slug: " . $expectedSlug);

            // --- CORREÇÃO 2: Sintaxe completa do updateOrCreate ---
            $room = Room::updateOrCreate(
                ['slug' => $expectedSlug], // Busca por este slug
                [ // Garante que estes dados estejam corretos
                    'name' => $expectedName,
                    'description' => 'Sala automática para account_id ' . (string)$accountId,
                    'is_private' => true,
                    'created_by' => $user->id,
                ]
            );

            // Se encontrou uma sala existente e o nome é diferente, atualiza.
            if ($room->wasRecentlyCreated === false && $room->name !== $expectedName) {
                $room->name = $expectedName;
                $room->save();
                Log::warning("ChatAutoLogin: Sala existente encontrada com slug, nome atualizado.", ['roomId' => $room->id]);
            }
            // --- FIM DA LÓGICA CORRIGIDA ---

            Log::info("ChatAutoLogin: Sala processada (ID: {$room->id}, Slug: {$room->slug}). Vinculando usuário...");
            $room->ensureUserMembership($user->id); // Garante que criador e usuário estão na sala
            Log::info("ChatAutoLogin: Usuário vinculado.");

            // Carrega permissões para incluir na resposta
            $user->load('roles', 'permissions');
            $permissions = $user->getAllPermissions()->pluck('name');

            // Monta a resposta CORRETA
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
                        'permissions' => $permissions,
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
                'email' => $email,
                'accountId' => $accountId,
                'exception_trace' => $e->getTraceAsString()
            ]);
            // --- CORREÇÃO 3: Resposta de erro 500 ---
            return response()->json([
                'success' => false,
                'message' => 'Erro interno no servidor durante o auto-login.',
                'error' => $e->getMessage() // Enviar a mensagem de erro real para depuração
            ], 500);
        }
    }
}
