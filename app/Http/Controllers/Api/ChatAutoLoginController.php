<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Room;
use Illuminate\Support\Str;
use Exception;

class ChatAutoLoginController extends Controller {
    /**
     * Auto login para integração com ChatRace
     * POST /api/v1/auth/auto-login
     */
    public function autoLogin(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'account_id' => 'required|string'
        ]);

        $email = $request->string('email');
        $accountId = $request->string('account_id');

        if ($email === '{{Email}}' || $accountId === '{{account_id}}') {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros de substituição inválidos.'
            ], 400);
        }

        try {
            // ... (cria usuário, cria token, cria sala) ...
            $user = User::firstOrCreate(...);
            $token = auth('api')->login($user);
            $slug = 'sala-' . Str::slug((string)$accountId);
            $room = Room::firstOrCreate(...);


            // 👇 CORREÇÃO AQUI 👇
            // Garante que o criador e o usuário atual (que é o mesmo neste caso) estejam na sala
            $room->ensureUserMembership($user->id); // Usa o método correto e passa o ID

            // Linha antiga (REMOVER):
            // $room->ensureCreatorMembership();

            // O syncWithoutDetaching abaixo talvez seja redundante se ensureUserMembership já faz isso,
            // mas não causa erro deixá-lo por segurança.
            $room->users()->syncWithoutDetaching([
                $user->id => ['joined_at' => now()]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Auto-login realizado com sucesso.',
                'token' => $token,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'room' => [
                        'id' => $room->id,
                        'slug' => $room->slug,
                        'name' => $room->name,
                        'description' => $room->description,
                    ],
                    'account_id' => (string)$accountId,
                    'redirect_to' => '/chat/room/' . $room->slug,
                ],
            ]);
        } catch (Exception $e) {
            // Loga o erro real antes de retornar a resposta genérica
            \Log::error('Erro no ChatAutoLoginController@autoLogin: ' . $e->getMessage(), ['exception' => $e]); // <-- ADICIONA LOG DETALHADO AQUI

            return response()->json([
                'success' => false,
                'message' => 'Erro no auto-login.',
                'error' => $e->getMessage(), // Opcional: remover $e->getMessage() em produção
            ], 500);
        }
    }
}
