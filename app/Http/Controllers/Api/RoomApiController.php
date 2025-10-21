<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth; // <-- ADICIONE ESTE IMPORT
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException; // <-- ADICIONE ESTE IMPORT

/**
 * @mixin \Illuminate\Foundation\Auth\Access\AuthorizesRequests
 */
class RoomApiController extends Controller {
    use AuthorizesRequests;

    public function index(Request $request)
    {
        \Log::info("--- RoomApiController@index Start ---");

        // Tenta pegar o usuário autenticado pelo Laravel
        $user = $request->user();

        // 👇 NOVA LÓGICA DE AUTENTICAÇÃO OPCIONAL 👇
        if (!$user) {
            \Log::info("User not found via \$request->user(). Trying JWTAuth::parseToken()->authenticate()");
            try {
                // Se não encontrou, tenta autenticar via JWT manualmente
                if ($token = JWTAuth::getToken()) { // Verifica se um token foi enviado
                    $user = JWTAuth::parseToken()->authenticate(); // Tenta validar e pegar o usuário
                    if ($user) {
                        \Log::info("User successfully authenticated via JWT", ['userId' => $user->id]);
                    } else {
                        \Log::info("JWT token found but user authentication failed.");
                    }
                } else {
                    \Log::info("No JWT token found in the request.");
                }
            } catch (JWTException $e) {
                // Se o token for inválido ou expirado, $user continua null (tratado como guest)
                \Log::warning("JWT Exception during optional authentication:", ['message' => $e->getMessage()]);
                $user = null; // Garante que $user seja null
            }
        }
        // 👆 FIM DA NOVA LÓGICA 👆


        $query = Room::query();
        \Log::info("Base query created.");

        // Agora o bloco if ($user) deve funcionar corretamente
        if ($user) {
            $userId = $user->id;
            \Log::info("User Authenticated (ID: {$userId}). Applying authenticated user filters.");

            $query->where(function ($q) use ($userId) {
                $q->where('is_private', false)
                    ->orWhere('created_by', $userId)
                    ->orWhereHas('users', function ($uq) use ($userId) {
                        $uq->where('user_id', $userId);
                    });
            });
        }
        // Lógica para Guest
        else {
            \Log::info("User is Guest. Applying WHERE is_private = false");
            $query->where('is_private', false);
        }

        \Log::info("SQL after WHERE clauses:", ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

        $query->with(['creator:id,name'])
            ->withCount('users');
        \Log::info("Added with(creator) and withCount(users).");

        try {
            \Log::info("FINAL SQL before execution:", ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
            $rooms = $query->latest()->paginate(20);
            \Log::info("Query Executed Successfully. Found rooms:", [ /* ... logs de contagem ... */ ]);

            // ... (o Log::warning se total === 0) ...

        } catch (\Exception $e) {
            \Log::error("FATAL ERROR during query execution:", [/* ... logs de erro ... */]);
            return response()->json(['error' => 'Failed to retrieve rooms'], 500);
        }

        \Log::info("--- RoomApiController@index End ---");
        return response()->json([ /* ... resposta JSON ... */ ]);
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
