<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateConversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrivateConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = PrivateConversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne', 'userTwo', 'latestMessage.sender'])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($userId) {
                $otherUser = $conversation->getOtherUser($userId);
                return [
                    'id' => $conversation->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'email' => $otherUser->email
                    ],
                    'latest_message' => $conversation->latestMessage ? [
                        'content' => $conversation->latestMessage->content,
                        'sender_id' => $conversation->latestMessage->sender_id,
                        'created_at' => $conversation->latestMessage->created_at
                    ] : null,
                    'last_message_at' => $conversation->last_message_at,
                    'updated_at' => $conversation->updated_at
                ];
            });

        return response()->json($conversations);
    }

    public function show(Request $request, PrivateConversation $conversation): JsonResponse
    {
        $userId = $request->user()->id;

        // Verificar se o usuário faz parte da conversa
        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $conversation->load(['userOne', 'userTwo', 'messages.sender']);

        $otherUser = $conversation->getOtherUser($userId);

        return response()->json([
            'id' => $conversation->id,
            'other_user' => [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'email' => $otherUser->email
            ],
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'sender' => [
                        'id' => $message->sender->id,
                        'name' => $message->sender->name
                    ],
                    'read_at' => $message->read_at,
                    'is_edited' => $message->is_edited,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at
                ];
            })
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id|different:' . $request->user()->id
        ]);

        $userId = $request->user()->id;
        $otherUserId = $request->user_id;

        $conversation = PrivateConversation::createConversation($userId, $otherUserId);

        $otherUser = User::find($otherUserId);

        return response()->json([
            'id' => $conversation->id,
            'other_user' => [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'email' => $otherUser->email
            ],
            'messages' => [],
            'created_at' => $conversation->created_at
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }
}
