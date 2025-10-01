<?php

/**
 * Modelo de usuário estendido com suporte
 * a tokens de API e relacionamentos do chat.
 */

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable implements JWTSubject
{

    use  HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    // --- Relacionamentos do Chat ---
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class)
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function createdRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function privateConversationsAsUserOne()
    {
        return $this->hasMany(PrivateConversation::class, 'user_one_id');
    }

    public function privateConversationsAsUserTwo()
    {
        return $this->hasMany(PrivateConversation::class, 'user_two_id');
    }

    public function privateConversations()
    {
        return $this->privateConversationsAsUserOne()
            ->union($this->privateConversationsAsUserTwo());
    }

    public function sentPrivateMessages()
    {
        return $this->hasMany(PrivateMessage::class, 'sender_id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
