<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // entrega imediata
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Message $message)
    {
        // Garante relações necessárias no payload
        $this->message->loadMissing(['user', 'room']);
    }

    public function broadcastOn(): array
    {
        // Sempre room.{slug}
        return [new PrivateChannel('room.' . $this->message->room->slug)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'room_id' => $this->message->room_id,
                'created_at' => optional($this->message->created_at)->toISOString(),
                'edited_at' => optional($this->message->edited_at)->toISOString(),
                'user' => [
                    'id' => $this->message->user->id,
                    'name' => $this->message->user->name,
                ],
            ],
        ];
    }
}
