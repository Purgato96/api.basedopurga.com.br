<?php

namespace App\Events;

use App\Models\PrivateMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(PrivateMessage $message)
    {
        $this->message = $message->load(['sender', 'conversation']);
    }

    public function broadcastOn()
    {
        $conversation = $this->message->conversation;

        return [
            new PrivateChannel('private-conversation.' . $conversation->id),
            new PrivateChannel('user.' . $conversation->user_one_id),
            new PrivateChannel('user.' . $conversation->user_two_id),
        ];
    }

    public function broadcastAs()
    {
        return 'private-message-sent';
    }

    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'sender' => [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name
                ],
                'conversation_id' => $this->message->private_conversation_id,
                'created_at' => $this->message->created_at->toISOString(),
                'is_edited' => $this->message->is_edited
            ]
        ];
    }
}
