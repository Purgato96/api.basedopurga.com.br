<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

// <-- ADICIONE ESTE IMPORT
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

// <-- ADICIONE ESTE IMPORT

/**
 * @mixin \Illuminate\Foundation\Auth\Access\AuthorizesRequests
 */
class RoomApiController extends Controller {
    use AuthorizesRequests;

    public function index(Request $request) {
        $user = $request->user();
        // Tenta autenticar manualmente se $request->user() falhar (código anterior)
        if (!$user) {
            try {
                if ($token = JWTAuth::getToken()) {
                    $user = JWTAuth::parseToken()->authenticate();
                }
            } catch (JWTException $e) {
                $user = null;
            }
        }

        \Log::info("--- RoomApiController@index Start (orWhereHas ISOLATION TEST) ---");

        if (!$user) {
            \Log::info("User is Guest. Skipping test.");
            return response()->json(['data_test' => []]); // Retorna vazio para guest
        }

        $userId = $user->id;
        \Log::info("User Authenticated for Test", ['userId' => $userId]);

        // 👇 TESTE: APENAS A CONDIÇÃO orWhereHas 👇
        $query = Room::query()
            ->whereHas('users', function ($uq) use ($userId) {
                $uq->where('user_id', $userId);
            });
        // 👆 FIM DO TESTE 👆

        // Loga a SQL e Bindings desta query específica
        \Log::info("ISOLATION TEST SQL (whereHas only):", ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

        try {
            // Executa a query de teste (usando get() para simplificar)
            $rooms = $query->get();

            \Log::info("ISOLATION TEST Query Executed. Found rooms:", [
                'count' => $rooms->count(),
                'ids' => $rooms->pluck('id')->toArray(), // Pega os IDs das salas encontradas
            ]);

        } catch (\Exception $e) {
            \Log::error("FATAL ERROR during ISOLATION TEST query:", ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed ISOLATION TEST query'], 500);
        }

        \Log::info("--- RoomApiController@index End (orWhereHas ISOLATION TEST) ---");
        // Retorna o resultado do teste diretamente
        return response()->json(['data_test_results' => $rooms]);
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
