<?php

use App\Models\PrivateConversation;
use App\Models\Room;
use App\Models\User; // <-- Adicione este import
use Illuminate\Support\Facades\Broadcast;

// Sala por slug
Broadcast::channel('room.{slug}', function (User $user, string $slug) { // <-- Adicione User type hint
    $room = Room::where('slug', $slug)->first();
    return $room && $user->can('view', $room);
});

// Canal de presença (se usar Echo.presence)
Broadcast::channel('room.{slug}.presence', function (User $user, string $slug) { // <-- Adicione User type hint
    $room = Room::where('slug', $slug)->first();


    if (!$room || !$user->can('view', $room)) {
        return false;
    }
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

// Alternativa por ID (se você usar canais com ID)
Broadcast::channel('room.{roomId}', function (User $user, int $roomId) {
    $room = Room::find($roomId);
    return $room && $user->can('view', $room);

});

// Conversas privadas (esta lógica já estava correta, sem userCanAccess)
Broadcast::channel('private-conversation.{conversationId}', function (User $user, int $conversationId) {
    $conversation = PrivateConversation::find($conversationId);
    return $conversation &&
        ($conversation->user_one_id === $user->id || $conversation->user_two_id === $user->id);
});

// Canal do usuário (esta lógica já estava correta)
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});
