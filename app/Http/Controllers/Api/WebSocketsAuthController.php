<?php

/**
 * Fornece autenticação e listagem de
 * canais privados para conexões WebSocket
 * externas usando Pusher.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebSocketsAuthController extends Controller {
    /**
     * Autentica usuário para canais privados do WebSocket
     */
    public function authenticate(Request $request) {
        $request->validate([
            'socket_id' => 'required|string',
            'channel_name' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado',
                'message' => 'Token de autenticação inválido ou expirado.'
            ], 401);
        }

        $channelName = $request->channel_name;
        $socketId = $request->socket_id;

        // Verifica se é um canal privado de sala
        if (preg_match('/^private-room\.(\d+)$/', $channelName, $matches)) {
            $roomId = $matches[1];
            $room = Room::find($roomId);

            if (!$room) {
                return response()->json([
                    'error' => 'Sala não encontrada',
                    'message' => 'A sala especificada não existe.'
                ], 404);
            }

            // Verifica se o usuário tem acesso à sala
            if ($room->is_private && !$room->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar esta sala.'
                ], 403);
            }

            // Gera a assinatura de autenticação do Pusher
            $pusher = app('pusher');
            $auth = $pusher->socket_auth($channelName, $socketId);

            return response()->json([
                'auth' => $auth,
                'channel_data' => json_encode([
                    'user_id' => $user->id,
                    'user_info' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ]
                ])
            ]);
        }

        // Verifica se é um canal de presença
        if (preg_match('/^presence-room\.(\d+)$/', $channelName, $matches)) {
            $roomId = $matches[1];
            $room = Room::find($roomId);

            if (!$room) {
                return response()->json([
                    'error' => 'Sala não encontrada',
                    'message' => 'A sala especificada não existe.'
                ], 404);
            }

            // Verifica acesso à sala
            if ($room->is_private && !$room->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar esta sala.'
                ], 403);
            }

            $pusher = app('pusher');
            $presence_data = [
                'user_id' => $user->id,
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ]
            ];

            $auth = $pusher->presence_auth($channelName, $socketId, $user->id, $presence_data);

            return response()->json([
                'auth' => $auth,
                'channel_data' => json_encode($presence_data)
            ]);
        }

        return response()->json([
            'error' => 'Canal inválido',
            'message' => 'O canal especificado não é válido.'
        ], 400);
    }

    /**
     * Lista canais disponíveis para o usuário
     */
    public function channels(Request $request) {
        $user = $request->user();

        $rooms = $user->rooms()->select('id', 'name', 'is_private')->get();

        $channels = $rooms->map(function ($room) {
            return [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'is_private' => $room->is_private,
                'channels' => [
                    'private' => "private-room.{$room->id}",
                    'presence' => "presence-room.{$room->id}",
                    'public' => $room->is_private ? null : "public.room.{$room->id}",
                ]
            ];
        });

        return response()->json([
            'data' => $channels,
            'websocket_config' => [
                'host' => config('broadcasting.connections.pusher.options.host'),
                'port' => config('broadcasting.connections.pusher.options.port'),
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            ]
        ]);
    }

    /**
     * Testa conexão WebSocket
     */
    public function test(Request $request) {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Conexão WebSocket disponível',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
