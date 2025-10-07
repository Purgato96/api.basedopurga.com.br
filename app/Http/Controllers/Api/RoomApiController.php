<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoomApiController extends Controller {
    /**
     * Lista salas visíveis ao usuário (públicas + privadas que ele participa).
     */
    public function index(Request $request) {
        $user = $request->user();

        $query = Room::with(['creator:id,name', 'users:id,name'])
            ->withCount('users', 'messages');

        if ($user) {
            $userId = $user->id;

            $query->where(function ($q) use ($userId) {
                $q->where('is_private', false)
                    ->orWhere('created_by', $userId)
                    ->orWhereHas('users', function ($uq) use ($userId) {
                        $uq->where('user_id', $userId);
                    });
            });
        } else {
            $query->where('is_private', false);
        }

        $rooms = $query->latest()->paginate(20);

        return response()->json([
            'data' => $rooms->items(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'last_page' => $rooms->lastPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
            ],
        ]);
    }

    /**
     * Exibe detalhes de uma sala específica.
     */
    public function show(Request $request, Room $room) {
        $userId = (int)optional($request->user())->id;

        if (!$room->userCanAccess($userId)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para acessar esta sala.',
            ], 403);
        }

        $room->load(['creator:id,name', 'users:id,name']);
        $room->loadCount('users', 'messages');

        return response()->json(['data' => $room]);
    }

    /**
     * Cria uma nova sala.
     */
    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_private' => 'boolean',
        ]);

        $userId = $request->user()->id;

        $room = Room::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(6),
            'description' => $request->description,
            'is_private' => $request->boolean('is_private'),
            'created_by' => $userId,
        ]);

        // O boot() do model já garante o attach do criador
        $room->load(['creator:id,name', 'users:id,name']);

        return response()->json([
            'data' => $room,
            'message' => 'Sala criada com sucesso.',
        ], 201);
    }

    /**
     * Atualiza uma sala.
     */
    public function update(Request $request, Room $room) {
        if ((int)$room->created_by !== (int)$request->user()->id) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Apenas o criador pode editar esta sala.',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_private' => 'sometimes|boolean',
        ]);

        $room->update($request->only(['name', 'description', 'is_private']));

        $room->load(['creator:id,name', 'users:id,name']);

        return response()->json([
            'data' => $room,
            'message' => 'Sala atualizada com sucesso.',
        ]);
    }

    /**
     * Exclui uma sala.
     */
    public function destroy(Request $request, Room $room) {
        if ((int)$room->created_by !== (int)$request->user()->id) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Apenas o criador pode deletar esta sala.',
            ], 403);
        }

        $room->delete();

        return response()->json(['message' => 'Sala deletada com sucesso.']);
    }

    /**
     * Usuário entra em uma sala.
     */
    public function join(Request $request, Room $room) {
        $userId = $request->user()->id;

        if (!$room->userCanAccess($userId)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Esta sala é privada.',
            ], 403);
        }

        if (!$room->users()->where('user_id', $userId)->exists()) {
            $room->users()->attach($userId, ['joined_at' => now()]);
        }

        return response()->json([
            'message' => 'Você entrou na sala com sucesso.',
            'data' => [
                'room_id' => $room->id,
                'user_id' => $userId,
                'joined_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Adiciona membro a uma sala privada (apenas o criador pode).
     */
    public function addMember(Request $request, Room $room) {
        if ((int)$room->created_by !== (int)$request->user()->id) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Apenas o criador pode adicionar membros.',
            ], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($room->users()->where('user_id', $data['user_id'])->exists()) {
            return response()->json([
                'message' => 'Usuário já é membro da sala.',
            ], 200);
        }

        $room->users()->attach($data['user_id'], ['joined_at' => now()]);

        return response()->json([
            'message' => 'Membro adicionado com sucesso.',
        ], 201);
    }

    /**
     * Usuário sai de uma sala.
     */
    public function leave(Request $request, Room $room) {
        $userId = $request->user()->id;
        $room->users()->detach($userId);

        return response()->json(['message' => 'Você saiu da sala com sucesso.']);
    }

    /**
     * Lista os membros de uma sala.
     */
    public function members(Request $request, Room $room) {
        $userId = (int)optional($request->user())->id;

        if (!$room->userCanAccess($userId)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para ver os membros desta sala.',
            ], 403);
        }

        $members = $room->users()
            ->select('users.id', 'users.name', 'users.email', 'room_user.joined_at')
            ->paginate(50);

        return response()->json([
            'data' => $members->items(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    /**
     * Lista todas as salas privadas em que o usuário participa ou criou.
     */
    public function myPrivateRooms(Request $request) {
        $userId = $request->user()->id;

        $rooms = Room::where('is_private', true)
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('users', function ($uq) use ($userId) {
                        $uq->where('user_id', $userId);
                    });
            })
            ->with(['users' => function ($q) use ($userId) {
                $q->where('users.id', '!=', $userId)
                    ->select('users.id', 'users.name', 'users.email');
            }])
            ->get();

        return response()->json(['data' => $rooms]);
    }
}
