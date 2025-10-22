<?php

namespace App\Http\Controllers\Api;

use App\Events\PrivateMessageSent;
use App\Http\Controllers\Controller;
use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrivateMessageController extends Controller
{
    public function store(Request $request, PrivateConversation $conversation): JsonResponse
    {
        $userId = $request->user()->id;

        // Verificar se o usuário faz parte da conversa
        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $message = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'content' => $request->content
        ]);

        // Atualizar timestamp da conversa
        $conversation->update(['last_message_at' => now()]);

        $message->load('sender');

        // Broadcast da mensagem
        broadcast(new PrivateMessageSent($message))->toOthers();

        return response()->json([
            'id' => $message->id,
            'content' => $message->content,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name
            ],
            'conversation_id' => $message->private_conversation_id,
            'created_at' => $message->created_at,
            'is_edited' => $message->is_edited
        ], 201);
    }

    public function update(Request $request, PrivateConversation $conversation, PrivateMessage $message): JsonResponse
    {
        $userId = $request->user()->id;

        // Verificar permissões
        if ($message->sender_id !== $userId || $message->private_conversation_id !== $conversation->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $message->update([
            'content' => $request->content,
            'is_edited' => true
        ]);

        return response()->json([
            'id' => $message->id,
            'content' => $message->content,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name
            ],
            'conversation_id' => $message->private_conversation_id,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
            'is_edited' => $message->is_edited
        ]);
    }

    public function markAsRead(Request $request, PrivateConversation $conversation, PrivateMessage $message): JsonResponse
    {
        $userId = $request->user()->id;

        // Verificar se o usuário faz parte da conversa e não é o remetente
        if (($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) ||
            $message->sender_id === $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->markAsRead();

        return response()->json(['message' => 'Message marked as read']);
    }
}
