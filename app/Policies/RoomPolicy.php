<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy {
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    /*public function view(User $user, Room $room): bool {
        // 1. Se a sala for pública, qualquer um pode ver.
        if (!$room->is_private) {
            return true;
        }

        // 2. Se for privada, verifique se o usuário é membro.
        if ($room->users->contains($user)) {
            return true;
        }

        // 3. Se nenhuma das condições acima for atendida, negue o acesso.
        return false;
    }*/
    public function view(User $user, Room $room): bool {
        // 1. Se a sala for pública, permite.
        if (!$room->is_private) {
            return true;
        }

        // 2. Se for privada, verifica se o ID do usuário está na lista de IDs dos membros da sala.
        //    Certifique-se de que a relação 'users' foi carregada com ->with('users') no controller!
        return $room->users->contains('id', $user->id); // <-- MUDANÇA AQUI

        // O return false implícito acontece se nenhuma condição for atendida.
    }
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Room $room): bool {
        return $user->id === $room->created_by;

    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Room $room): bool {
        return $user->id === $room->created_by;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Room $room): bool {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Room $room): bool {
        return false;
    }

    public function addMember(User $user, Room $room): bool {
        return $user->hasRole('Master') || $user->id === $room->created_by;
    }

    public function leave(User $user, Room $room): bool {
        return (!(($user->id) === $room->created_by) && $room->users->contains($user)) || $user->hasRole('Master');
    }
}
