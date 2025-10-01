<?php

/**
 * Representa uma mensagem enviada dentro
 * de uma sala de chat.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'user_id',
        'room_id',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    protected $with = ['user'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isEdited(): bool
    {
        return !is_null($this->edited_at);
    }
}
