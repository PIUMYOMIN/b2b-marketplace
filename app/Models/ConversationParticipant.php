<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    public const ROLE_BUYER = 'buyer';
    public const ROLE_SELLER = 'seller';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_at',
        'muted_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'user_id' => 'integer',
        'last_read_at' => 'datetime',
        'muted_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRead(): void
    {
        $this->last_read_at = now();
        $this->save();
    }
}
