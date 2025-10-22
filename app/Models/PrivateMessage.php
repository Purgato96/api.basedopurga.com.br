<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'private_conversation_id',
        'sender_id',
        'content',
        'read_at',
        'is_edited'
    ];

    protected $dates = [
        'read_at'
    ];

    protected $casts = [
        'is_edited' => 'boolean'
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(PrivateConversation::class, 'private_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
