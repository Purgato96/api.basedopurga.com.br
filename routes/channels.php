<?php

use App\Models\PrivateConversation;
use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

// Sala por slug
Broadcast::channel('room.{slug}', function ($user, $slug) {
    $room = Room::where('slug', $slug)->first();
    return $room ? $room->userCanAccess($user->id) : false;
});

// Canal de presença (se usar Echo.presence)
Broadcast::channel('room.{slug}.presence', function ($user, $slug) {
    $room = Room::where('slug', $slug)->first();
    if (! $room) return false;
    if (! $room->userCanAccess($user->id)) return false;

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

// Alternativa por ID
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $room = Room::find($roomId);
    return $room ? $room->userCanAccess($user->id) : false;
});

// Conversas privadas
Broadcast::channel('private-conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = PrivateConversation::find($conversationId);
    return $conversation &&
        ($conversation->user_one_id === $user->id || $conversation->user_two_id === $user->id);
});

// Canal do usuário
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
