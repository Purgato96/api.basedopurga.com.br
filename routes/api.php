<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatAutoLoginController;
use App\Http\Controllers\Api\MessageApiController;
use App\Http\Controllers\Api\PrivateConversationController;
use App\Http\Controllers\Api\PrivateMessageController;
use App\Http\Controllers\Api\RoomApiController;
use App\Http\Controllers\Api\WebSocketsAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    // Público
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/auto-login', [ChatAutoLoginController::class, 'autoLogin']);
    Route::get('/rooms', [RoomApiController::class, 'index']); // Listagem geral PODE ser pública
});

Route::prefix('v1')->name('api.')->middleware(['auth:api'])->group(function () {
    // Rotas de Autenticação Protegidas
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // --- ROTAS DE SALA ESPECÍFICA (MOVIMOS PARA CÁ) ---
    Route::get('/rooms/{room:slug}', [RoomApiController::class, 'show']);          // Requer auth
    Route::get('/rooms/{room:slug}/members', [RoomApiController::class, 'members']); // Requer auth
    Route::get('/rooms/{room:slug}/messages', [MessageApiController::class, 'index']); // Requer auth
    Route::get('/rooms/{room:slug}/messages/search', [MessageApiController::class, 'search']); // Requer auth
    Route::get('/messages/{message}', [MessageApiController::class, 'show']);      // Requer auth (geralmente)

    // --- ROTAS DE MODIFICAÇÃO DE SALA ---
    Route::post('/rooms', [RoomApiController::class, 'store']);
    Route::put('/rooms/{room:slug}', [RoomApiController::class, 'update']);
    Route::delete('/rooms/{room:slug}', [RoomApiController::class, 'destroy']);

    // --- ROTAS DE AÇÃO EM SALA ---
    Route::post('/rooms/{room:slug}/join', [RoomApiController::class, 'join']);
    Route::post('/rooms/{room:slug}/members', [RoomApiController::class, 'addMember']);
    Route::delete('/rooms/{room:slug}/leave', [RoomApiController::class, 'leave']);
    Route::get('/rooms/private/all', [RoomApiController::class, 'myPrivateRooms']);

    // --- ROTAS DE MENSAGEM ---
    Route::post('/rooms/{room:slug}/messages', [MessageApiController::class, 'store']);
    Route::put('/messages/{message}', [MessageApiController::class, 'update']);
    Route::delete('/messages/{message}', [MessageApiController::class, 'destroy']);

    // --- ROTAS DE CONVERSA PRIVADA ---
    Route::get('/private-conversations', [PrivateConversationController::class, 'index']);
    Route::post('/private-conversations', [PrivateConversationController::class, 'start']);
    Route::get('/private-conversations/{conversation}', [PrivateConversationController::class, 'show']);
    Route::post('/private-conversations/{conversation}/messages', [PrivateMessageController::class, 'store']);
    Route::put('/private-conversations/{conversation}/messages/{message}', [PrivateMessageController::class, 'update']);
    Route::post('/private-conversations/{conversation}/messages/{message}/read', [PrivateMessageController::class, 'markAsRead']);

    // --- ROTAS DE WEBSOCKET ---
    Route::post('/websocket/auth', [WebSocketsAuthController::class, 'authenticate']);
    Route::get('/websocket/channels', [WebSocketsAuthController::class, 'channels']);
    Route::get('/websocket/test', [WebSocketsAuthController::class, 'test']);
});

Route::get('/v1/status', function () {
    return response()->json([
        'status' => 'online',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
        'endpoints' => [
            'auth' => '/api/v1/auth/*',
            'rooms' => '/api/v1/rooms',
            'messages' => '/api/v1/rooms/{room}/messages',
            'private-conversations' => '/api/v1/private-conversations',
            'websocket' => 'pusher.com/channels',
        ]
    ]);
});

Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint não encontrado',
        'message' => 'A rota solicitada não existe. Consulte a documentação da API.',
        'documentation' => '/api/v1/status'
    ], 404);
});
