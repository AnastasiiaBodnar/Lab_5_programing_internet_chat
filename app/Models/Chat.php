<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants')
            ->withPivot('joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function hasParticipant($userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function getOtherParticipant($currentUserId)
    {
        if ($this->type === 'private') {
            return $this->participants()->where('user_id', '!=', $currentUserId)->first();
        }
        return null;
    }
}
