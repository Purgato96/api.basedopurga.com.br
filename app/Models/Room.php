<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model {
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_private',
        'created_by',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    /**
     * Criador da sala.
     */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuários membros da sala.
     */
    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class)
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Mensagens da sala.
     */
    public function messages(): HasMany {
        return $this->hasMany(Message::class);
    }

    /**
     * Últimas mensagens (limite 50).
     */
    public function latestMessages(): HasMany {
        return $this->hasMany(Message::class)
            ->with('user')
            ->latest()
            ->limit(50);
    }

    /**
     * Usa o slug na rota.
     */
    public function getRouteKeyName(): string {
        return 'slug';
    }

    /**
     * Verifica se o usuário pode acessar a sala.
     */
    public function userCanAccess(int $userId): bool {
        if (!$this->is_private) return true;
        if ((int)$this->created_by === $userId) return true;

        return $this->users()->where('user_id', $userId)->exists();
    }

    /**
     * Garante que o criador seja sempre membro da sala após a criação.
     */
    protected static function booted(): void {
        static::created(function (self $room) {
            if ($room->created_by && !$room->users()->where('user_id', $room->created_by)->exists()) {
                $room->users()->attach($room->created_by, ['joined_at' => now()]);
            }
        });
    }
}
