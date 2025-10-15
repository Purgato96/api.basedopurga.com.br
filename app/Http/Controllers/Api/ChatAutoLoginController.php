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
            // Cria ou encontra o usuário
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => (string)$email,
                    'password' => bcrypt(Str::random(16)),
                    'account_id' => (string)$accountId
                ]
            );

            // Cria token JWT sem expiração
            $token = auth('api')->login($user);

            // Slug da sala baseado no account_id
            $slug = 'sala-' . Str::slug((string)$accountId);

            // Cria ou encontra a sala
            $room = Room::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => 'Espaço #' . (string)$accountId,
                    'description' => 'Sala automática para account_id ' . (string)$accountId,
                    'is_private' => true,
                    'created_by' => $user->id,
                ]
            );

            // Garante que o criador SEMPRE esteja na sala
            $room->ensureCreatorMembership();

            // Garante que o usuário atual também esteja vinculado
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
            return response()->json([
                'success' => false,
                'message' => 'Erro no auto-login.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
