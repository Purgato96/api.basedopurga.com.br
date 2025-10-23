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
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => (string)$email,
                    'password' => Hash::make(Str::random(16)), // <-- USA HASH::make()
                    'account_id' => (string)$accountId
                ]
            );

            // 👇 TESTE DE SANIDADE 👇
            if (!$user instanceof \App\Models\User) {
                Log::critical("ChatAutoLogin: ERRO GRAVE - User::firstOrCreate não retornou um objeto User!", ['result' => gettype($user)]);
                throw new Exception("Falha ao obter objeto do usuário.");
            }
            Log::info("ChatAutoLogin: Usuário encontrado/criado com ID: " . $user->id);
            // 👆 FIM DO TESTE 👆

            // 👇 GERA O TOKEN DIRETAMENTE PELA FACADE 👇
            $token = JWTAuth::fromUser($user);
            Log::info("ChatAutoLogin: Token gerado com sucesso.");
            // Linha antiga (Comentada):
            // $token = auth('api')->login($user);


            // ... (cria/encontra sala, ensureUserMembership, syncWithoutDetaching) ...
            $slug = 'sala-' . Str::slug((string)$accountId);
            $room = Room::firstOrCreate( /*...*/ );
            $room->ensureUserMembership($user->id);
            $room->users()->syncWithoutDetaching([$user->id => ['joined_at' => now()]]);
            Log::info("ChatAutoLogin: Sala processada e usuário vinculado.");


            // Retorna a resposta
            Log::info("ChatAutoLogin: Sucesso. Retornando JSON.");
            return response()->json([ /* ... sua resposta JSON ... */ ]);

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
