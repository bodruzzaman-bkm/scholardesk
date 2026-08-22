<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = ['chat_session_id', 'role', 'content', 'citations'];

    protected function casts(): array
    {
        return ['citations' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
