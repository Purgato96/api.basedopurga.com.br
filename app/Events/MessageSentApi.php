<?php

/**
 * Versão do evento para consumo de clientes
 * externos via API, transmitindo apenas
 * dados essenciais da mensagem.
 */

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSentApi implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.' . $this->message->slug)];
    }


    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->message->content,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
            ],
            'room_id' => $this->message->room_id,
            'created_at' => $this->message->created_at->toISOString(),
            'edited_at' => $this->message->edited_at?->toISOString(),
            'is_edited' => !is_null($this->message->edited_at),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Determina se o evento deve ser transmitido
     */
    public function shouldBroadcast(): bool
    {
        // Só transmite se a sala não for privada ou se for um canal privado
        return !$this->message->room->is_private || $this->socket !== null;
    }
}
