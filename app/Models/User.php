<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'status',
        'last_login_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    private ?array $permissionNames = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function hasPermission(string $permission): bool
    {
        $this->permissionNames ??= $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();

        return in_array($permission, $this->permissionNames, true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function roleLabel(): string
    {
        $this->loadMissing('roles');

        return $this->roles->pluck('name')->join(', ') ?: 'User';
    }

    public function isSystemAdministrator(): bool
    {
        $this->loadMissing('roles:id,name');

        return $this->roles->contains(fn (Role $role) => $role->name === 'System Administrator');
    }

    public function canEditTicketComment(TicketComment $comment): bool
    {
        return (int) $comment->user_id === (int) $this->id;
    }

    public function canDeleteTicketComment(TicketComment $comment): bool
    {
        return $this->canEditTicketComment($comment) || $this->isSystemAdministrator();
    }

    /**
     * Requester-only staff: can open and follow their own cases, not the ICT queue.
     */
    public function isEmployeeRequester(): bool
    {
        return $this->hasPermission('tickets.view.own')
            && ! $this->hasAnyPermission(['tickets.view.department', 'tickets.view.all', 'tickets.assign', 'tickets.update']);
    }

    public function limitsRecordsToDepartment(): bool
    {
        if ($this->department_id === null) {
            return false;
        }

        if ($this->hasPermission('audit.view') || $this->hasPermission('assets.update') || $this->hasPermission('assets.create') || $this->hasPermission('network.view')) {
            return false;
        }

        return true;
    }
}
