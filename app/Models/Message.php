<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'user_id',
        'message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(MessageStatus::class);
    }

    public function getStatusForUser($userId)
    {
        return $this->statuses()->where('user_id', $userId)->first();
    }

    public function isReadByAll(): bool
    {
        return $this->statuses()->where('status', '!=', 'read')->count() === 0;
    }

    public function isDeliveredToAll(): bool
    {
        return $this->statuses()
                ->whereIn('status', ['sent'])
                ->count() === 0;
    }
}
