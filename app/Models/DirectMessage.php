<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'subject',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('sender_id', $user->id)
                ->orWhere('recipient_id', $user->id);
        });
    }

    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->where('recipient_id', $user->id)->whereNull('read_at');
    }

    public function counterpart(User $viewer): ?User
    {
        if ($this->sender_id === $viewer->id) {
            return $this->recipient;
        }

        if ($this->recipient_id === $viewer->id) {
            return $this->sender;
        }

        return null;
    }

    public function isUnreadFor(User $viewer): bool
    {
        return $this->recipient_id === $viewer->id && $this->read_at === null;
    }
}
