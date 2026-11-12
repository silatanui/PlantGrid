<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number', 'requester_id', 'department_id', 'ticket_category_id', 'asset_id',
        'system_application_id', 'subject', 'description', 'priority', 'status', 'assigned_to',
        'reported_at', 'due_at', 'sla_due_at', 'sla_breached_at', 'sla_at_risk_notified_at',
        'resolved_at', 'closed_at', 'resolution_notes',
        'close_reason', 'duplicate_of_ticket_id',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'due_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'sla_at_risk_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_ticket_partners')
            ->withPivot('source')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SystemApplication::class, 'system_application_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_ticket_id');
    }

    public function isPartner(User $user): bool
    {
        if ((int) $this->requester_id === (int) $user->id) {
            return true;
        }

        if ($this->assigned_to && (int) $this->assigned_to === (int) $user->id) {
            return true;
        }

        if ($this->relationLoaded('partners')) {
            return $this->partners->contains('id', $user->id);
        }

        return $this->partners()->where('users.id', $user->id)->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Signed-in staff can open any case; the queue still prioritises role scope
        // while always including cases they own, requested, or partner on.
        if ($user->hasPermission('tickets.view.all') || $user->hasPermission('audit.view')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            if ($user->hasPermission('tickets.view.department') && $user->department_id) {
                $inner->where('department_id', $user->department_id);
            } else {
                $inner->where('requester_id', $user->id);
            }

            $inner->orWhere('requester_id', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->orWhereHas('partners', fn (Builder $partners) => $partners->where('users.id', $user->id));
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }
}
