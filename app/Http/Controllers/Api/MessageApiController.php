<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use Illuminate\Http\Request;

class MessageApiController extends Controller {
    /**
     * Lista mensagens de uma sala
     */
    public function index(Request $request, Room $room) {
        $userId = (int)optional($request->user())->id;

        // Usa regra centralizada
        if (!$room->userCanAccess($userId)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para ver as mensagens desta sala.'
            ], 403);
        }

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
        $userId = (int)optional($request->user())->id;

        if (!$room->userCanAccess($userId)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para ver esta mensagem.'
            ], 403);
        }

        $message->load('user:id,name', 'room:id,name');

        return response()->json([
            'data' => $message
        ]);
    }

    /**
     * Envia uma nova mensagem
     */
    public function store(Request $request, Room $room) {
        $userId = $request->user()->id;

        // Usa regra centralizada: criador OU membro podem enviar
        if (!$room->userCanAccess($userId)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para enviar mensagens nesta sala.'
            ], 403);
        }

        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'content' => $request->input('content'),
            'user_id' => $userId,
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
    }

    /**
     * Atualiza uma mensagem (apenas o autor)
     */
    public function update(Request $request, Message $message) {
        if ((int)$message->user_id !== (int)$request->user()->id) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você só pode editar suas próprias mensagens.'
            ], 403);
        }

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
        if ((int)$message->user_id !== (int)$request->user()->id) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você só pode deletar suas próprias mensagens.'
            ], 403);
        }

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
        $userId = (int)optional($request->user())->id;

        if (!$room->userCanAccess($userId)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para buscar nesta sala.'
            ], 403);
        }

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
