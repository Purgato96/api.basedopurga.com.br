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
    public function view(User $user, Room $room): bool {
        // 👇 ADICIONE ESTA VERIFICAÇÃO PRIMEIRO 👇
        // 1. Se for Master ou Admin, permite acesso a QUALQUER sala.
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // 2. Se a sala for pública, permite.
        if (!$room->is_private) {
            return true;
        }

        // 3. Se for privada E o usuário não for Master/Admin,
        //    verifica se ele é membro.
        return $room->users()->where('user_id', $user->id)->exists();
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
