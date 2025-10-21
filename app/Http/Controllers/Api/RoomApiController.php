<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @mixin \Illuminate\Foundation\Auth\Access\AuthorizesRequests
 */
class RoomApiController extends Controller {
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();
        \Log::info("--- RoomApiController@index Start ---"); // Log início

        // Cria a query base
        $query = Room::query();
        \Log::info("Base query created.");

        // Se o usuário está logado, aplica o filtro de acesso
        if ($user) {
            $userId = $user->id;
            \Log::info("User Authenticated", ['userId' => $userId, 'userName' => $user->name]); // Loga ID e nome

            $query->where(function ($q) use ($userId) {
                \Log::info("Applying WHERE clause group...");

                // Condição 1: Sala Pública
                $q->where('is_private', false);
                \Log::info(" -> Added condition: is_private = false");

                // Condição 2: Criador é o usuário
                $q->orWhere('created_by', $userId);
                \Log::info(" -> Added OR condition: created_by = " . $userId . " (Type: " . gettype($userId) . ")"); // Loga tipo

                // Condição 3: Usuário é membro
                $q->orWhereHas('users', function ($uq) use ($userId) {
                    \Log::info(" ---> Inside orWhereHas for users...");
                    $uq->where('user_id', $userId);
                    \Log::info(" -----> Added sub-condition: user_id = " . $userId);
                });
                \Log::info(" -> Added OR condition: User is member via orWhereHas");
            });
            \Log::info("Finished applying WHERE clause group.");

        }
        // Se não está logado (visitante)
        else {
            \Log::info("User is Guest. Applying WHERE is_private = false");
            $query->where('is_private', false);
        }

        // Loga a SQL e Bindings ANTES de adicionar relations/counts
        \Log::info("SQL after WHERE clauses:", ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

        // Carrega relações e contagem
        $query->with(['creator:id,name'])
            ->withCount('users');
        \Log::info("Added with(creator) and withCount(users).");


        // Executa a query final
        try {
            // Loga a SQL FINAL antes de executar
            \Log::info("FINAL SQL before execution:", ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

            $rooms = $query->latest()->paginate(20);

            \Log::info("Query Executed Successfully. Found rooms:", [
                'total' => $rooms->total(),
                'items_count' => count($rooms->items()),
                'current_page' => $rooms->currentPage(),
            ]); // Loga contagem

            // Se encontrou 0, loga as salas ANTES do filtro (se tivéssemos filtro)
            if ($rooms->total() === 0 && $user) {
                $allUserRoomsDirectly = $user->rooms()->pluck('rooms.id')->toArray();
                $allCreatedRoomsDirectly = $user->createdRooms()->pluck('rooms.id')->toArray();
                $allPublicRooms = Room::where('is_private', false)->pluck('id')->toArray();
                \Log::warning("Query returned 0 results. Checking directly:", [
                    'user_is_member_of_room_ids' => $allUserRoomsDirectly,
                    'user_created_room_ids' => $allCreatedRoomsDirectly,
                    'public_room_ids' => $allPublicRooms,
                ]);
            }


        } catch (\Exception $e) {
            \Log::error("FATAL ERROR during query execution:", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to retrieve rooms'], 500);
        }

        // Retorna a resposta JSON
        \Log::info("--- RoomApiController@index End ---"); // Log fim
        return response()->json([
            'data' => $rooms->items(),
            'meta' => [ /* ... meta info ... */ ],
        ]);
    }

    public function show(Request $request, Room $room) {
        $this->authorize('view', $room);
        $room->load(['creator:id,name', 'users:id,name']);
        $room->loadCount('users', 'messages');

        return response()->json(['data' => $room]);
    }

    public function store(Request $request) {
        $this->authorize('create', Room::class);
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

        // Garante criador na pivot com joined_at
        $room->users()->syncWithoutDetaching([$userId => ['joined_at' => now()]]);
        $room->load(['creator:id,name', 'users:id,name']);

        return response()->json([
            'data' => $room,
            'message' => 'Sala criada com sucesso.',
        ], 201);
    }

    public function update(Request $request, Room $room) {
        $this->authorize('update', $room);
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

    public function destroy(Request $request, Room $room) {
        $this->authorize('delete', $room);
        $room->delete();

        return response()->json(['message' => 'Sala deletada com sucesso.']);
    }

    public function join(Request $request, Room $room) {
        $userId = $request->user()->id;

        $this->authorize('view', $room);

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

    public function addMember(Request $request, Room $room) {
        // Apenas o criador pode adicionar
        $this->authorize('addMember', $room);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        if ($room->users()->where('user_id', $data['user_id'])->exists()) {
            return response()->json([
                'message' => 'Usuário já é membro da sala.'
            ], 200);
        }

        $room->users()->attach($data['user_id'], ['joined_at' => now()]);

        return response()->json([
            'message' => 'Membro adicionado com sucesso.'
        ], 201);
    }


    public function leave(Request $request, Room $room) {
        $userId = $request->user()->id;
        $this->authorize('leave', $room);
        // Se quiser evitar que o criador saia, bloqueie aqui
        $room->users()->detach($userId);

        return response()->json(['message' => 'Você saiu da sala com sucesso.']);
    }

    public function members(Request $request, Room $room) {

        $this->authorize('view', $room);

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
