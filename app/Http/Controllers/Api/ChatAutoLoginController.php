<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Room;
use Illuminate\Support\Str;

class ChatAutoLoginController extends Controller {
    /**
     * Autenticação automática via parâmetros (JWT)
     * Ex.: POST /api/v1/auth/auto-login { email, account_id }
     */
    public function autoLogin(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'account_id' => 'required|string'
        ]);

        $email = $request->string('email');
        $accountId = $request->string('account_id');

        if ($email === '{{Email}}') {
            return response()->json([
                'success' => false,
                'message' => 'Email não foi substituído corretamente'
            ], 400);
        }

        try {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => (string)$email,
                    'password' => bcrypt(Str::random(16)),
                    'account_id' => (string)$accountId
                ]
            );

            // JWT token
            $token = auth('api')->setTTL(0)->login($user);

            // Slug determinístico por account_id
            $slug = 'sala-' . (string)$accountId;
            $room = Room::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => 'Espaço #' . (string)$accountId,
                    'description' => 'Sala automática para account_id ' . (string)$accountId,
                    'is_private' => false,
                    'created_by' => $user->id,
                ]
            );

            // Vincula usuário à sala
            $room->users()->syncWithoutDetaching([$user->id => ['joined_at' => now()]]);

            return response()->json([
                'success' => true,
                'message' => 'Auto-login realizado com sucesso',
                'token' => $token,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'room' => [
                        'id' => $room->id,
                        'slug' => $room->slug,
                        'name' => $room->name,
                        'description' => $room->description
                    ],
                    'account_id' => (string)$accountId,
                    'redirect_to' => '/chat/room/' . $room->slug
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro no auto-login',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
