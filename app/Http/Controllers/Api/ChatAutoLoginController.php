<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash; // <-- Mude para Hash
use Illuminate\Support\Facades\Log;   // <-- Adicione Log
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth; // <-- Adicione JWTAuth

class ChatAutoLoginController extends Controller
{
    public function autoLogin(Request $request)
    {
        // ... (validação e checagem de placeholders) ...
        $request->validate([/*...*/]);
        $email = $request->string('email');
        $accountId = $request->string('account_id');
        if ($email === '{{Email}}' || $accountId === '{{account_id}}') { /*...*/ }

        try {
            Log::info("ChatAutoLogin: Iniciando para email: " . $email);

            // Cria ou encontra o usuário
            $user = User::firstOrCreate(...);
            $token = JWTAuth::fromUser($user);

            // --- LÓGICA DA SALA CORRIGIDA ---
            $expectedSlug = 'sala-' . Str::slug((string)$accountId);
            $expectedName = 'Espaço #' . (string)$accountId;

            // Usa updateOrCreate para garantir nome/slug corretos
            $room = Room::updateOrCreate(
                ['slug' => $expectedSlug], // Busca por este slug
                [ // Garante que estes dados estejam corretos (se criar ou se encontrar)
                    'name' => $expectedName,
                    'description' => 'Sala automática para account_id ' . (string)$accountId,
                    'is_private' => true,
                    'created_by' => $user->id,
                    // Não precisa de 'slug' aqui, pois já está na condição de busca
                ]
            );
            // Se encontrou uma sala existente, força a atualização do nome (caso raro)
            if ($room->wasRecentlyCreated === false && $room->name !== $expectedName) {
                $room->name = $expectedName;
                $room->save();
                Log::warning("ChatAutoLogin: Sala existente encontrada com slug correto, mas nome antigo. Nome atualizado.", ['roomId' => $room->id]);
            }
            // --- FIM DA LÓGICA CORRIGIDA ---


            Log::info("ChatAutoLogin: Sala processada (ID: {$room->id}, Slug: {$room->slug}). Vinculando usuário...");
            $room->ensureUserMembership($user->id);
            $room->users()->syncWithoutDetaching([$user->id => ['joined_at' => now()]]);
            Log::info("ChatAutoLogin: Usuário vinculado.");

            $user->load('roles', 'permissions');
            $permissions = $user->getAllPermissions()->pluck('name');
            $responseData = [
                'success' => true,
                'message' => 'Auto-login realizado com sucesso.',
                'token' => $token,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'account_id' => $user->account_id, // Inclui account_id
                        'permissions' => $permissions, // Inclui permissões
                    ],
                    'room' => [
                        'id' => $room->id,
                        'slug' => $room->slug, // Slug da sala específica
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
                'email' => $email, // Loga o email para ajudar a identificar
                'accountId' => $accountId,
                'exception_trace' => $e->getTraceAsString() // Loga o stack trace completo
            ]);
            return response()->json([ /* ... resposta 500 ... */ ], 500);
        }
    }
}
