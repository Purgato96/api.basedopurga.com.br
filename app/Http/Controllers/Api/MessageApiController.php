<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MessageApiController extends Controller {
    use AuthorizesRequests;
    /**
     * Lista mensagens de uma sala
     */
    public function index(Request $request, Room $room) {
        $this->authorize('view', $room);

        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'before' => 'sometimes|integer|exists:messages,id',
            'after' => 'sometimes|integer|exists:messages,id',
        ]);

        $query = $room->messages()
            ->with('user:id,name')
            ->latest();

        if ($request->has('before')) {
            $query->where('id', '<', $request->integer('before'));
        }

        if ($request->has('after')) {
            $query->where('id', '>', $request->integer('after'));
        }

        $perPage = (int)$request->get('per_page', 50);
        $messages = $query->limit($perPage)->get();

        if (!$request->has('after')) {
            $messages = $messages->reverse()->values();
        }

        return response()->json([
            'data' => $messages,
            'meta' => [
                'room_id' => $room->id,
                'count' => $messages->count(),
                'per_page' => $perPage,
                'has_more' => $messages->count() === $perPage,
            ]
        ]);
    }

    /**
     * Exibe uma mensagem específica
     */
    public function show(Request $request, Message $message) {
        $room = $message->room;
        $this->authorize('view', $room);

        $message->load('user:id,name', 'room:id,name');

        return response()->json([
            'data' => $message
        ]);
    }

    /**
     * Envia uma nova mensagem
     */
    /*public function store(Request $request, Room $room) {
        $user = $request->user(); // Pega o usuário logado

        // 1. Verifica se o usuário pode ACESSAR a sala (usa RoomPolicy@view)
        $this->authorize('view', $room);
        // 2. Verifica se o usuário tem a PERMISSÃO GERAL de enviar mensagens
        if (!$user->can('send-messages')) {
            // Você pode usar abort() ou retornar uma resposta JSON
            // abort(403, 'Você não tem permissão para enviar mensagens.');
            return response()->json(['error' => 'Acesso negado', 'message' => 'Você não tem permissão para enviar mensagens.'], 403);
        }

        // Se passou nas duas verificações, continua...
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'content' => $request->input('content'),
            'user_id' => $user->id, // Usa o $user que já pegamos
            'room_id' => $room->id,
        ]);

        // Carrega relações essenciais para o payload e canal
        $message->load(['user:id,name', 'room:id,slug']);

        // Dispara broadcast para os outros clientes
        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'data' => $message,
            'message' => 'Mensagem enviada com sucesso.'
        ], 201);
    }*/
    public function store(Request $request, Room $room) {
        $user = $request->user();
        Log::info("--- MessageApiController@store Start ---", ['userId' => $user?->id]); // Log início

        // 1. Verifica acesso à sala
        try {
            $this->authorize('view', $room);
            Log::info("Authorization ('view', room) PASSED."); // Log sucesso
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::error("Authorization ('view', room) FAILED.", ['exception' => $e->getMessage()]); // Log falha
            return response()->json(['error' => 'Acesso negado', 'message' => 'Você não tem permissão para ver esta sala.'], 403);
        }

        // 👇 LOG DETALHADO DAS PERMISSÕES 👇
        $permissionsArray = $user->getAllPermissions()->pluck('name')->toArray();
        Log::info("User permissions according to Spatie:", ['permissions' => $permissionsArray]);
        Log::info("Checking specifically for 'send-messages' permission...");
        // 👆 FIM DO LOG DETALHADO 👆

        // 2. Verifica permissão GERAL de enviar
        if (!$user->can('send-messages')) {
            Log::warning("PERMISSION CHECK FAILED: User CANNOT 'send-messages'. Returning 403."); // Log específico da falha
            return response()->json(['error' => 'Acesso negado', 'message' => 'Você não tem permissão para enviar mensagens.'], 403);
        }
        Log::info("Permission check ('send-messages') PASSED."); // Log sucesso

        // ... (resto do código store: validate, create, broadcast, return) ...
        Log::info("--- MessageApiController@store End ---"); // Log fim
    }

    /**
     * Atualiza uma mensagem (apenas o autor)
     */
    public function update(Request $request, Message $message) {
        $this->authorize('update', $message);

        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $message->update([
            'content' => $request->string('content'),
            'edited_at' => now(),
        ]);

        $message->load('user:id,name');

        // Opcional: emitir um evento de mensagem atualizada
        // broadcast(new MessageUpdated($message))->toOthers();

        return response()->json([
            'data' => $message,
            'message' => 'Mensagem atualizada com sucesso.'
        ]);
    }

    /**
     * Remove uma mensagem (apenas o autor)
     */
    public function destroy(Request $request, Message $message) {
        $this->authorize('delete', $message);

        $message->delete();

        // Opcional: emitir evento de exclusão
        // broadcast(new MessageDeleted($message->id, $message->room_id))->toOthers();

        return response()->json([
            'message' => 'Mensagem deletada com sucesso.'
        ]);
    }

    /**
     * Busca mensagens por conteúdo
     */
    public function search(Request $request, Room $room) {
        $this->authorize('view', $room);

        $request->validate([
            'q' => 'required|string|min:3|max:100',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $room->messages()
            ->with('user:id,name')
            ->where('content', 'LIKE', '%' . $request->q . '%')
            ->latest();

        $perPage = (int)$request->get('per_page', 20);
        $messages = $query->paginate($perPage);

        return response()->json([
            'data' => $messages->items(),
            'meta' => [
                'query' => $request->q,
                'room_id' => $room->id,
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]
        ]);
    }
}
