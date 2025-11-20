<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageStatus extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsDelivered(): void
    {
        if ($this->status === 'sent') {
            $this->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
        }
    }

    public function markAsRead(): void
    {
        if (in_array($this->status, ['sent', 'delivered'])) {
            $this->update([
                'status' => 'read',
                'read_at' => now(),
                'delivered_at' => $this->delivered_at ?? now(),
            ]);
        }
    }
}
