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
        'job_title',
        'phone',
        'mobile',
        'extension',
        'address',
        'city',
        'postal_code',
        'country',
    ];

    public function contactLine(): string
    {
        $parts = array_filter([
            $this->phone ? 'Desk '.$this->phone : null,
            $this->mobile ? 'Mobile '.$this->mobile : null,
            $this->extension ? 'Ext '.$this->extension : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'No phone on file';
    }

    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->postal_code,
            $this->country,
        ]);

        return $parts !== [] ? implode(', ', $parts) : 'No address on file';
    }

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

    public function shortRoleLabel(): string
    {
        $this->loadMissing('roles:id,name');
        $name = (string) ($this->roles->first()?->name ?: 'User');

        return match ($name) {
            'System Administrator' => 'Admin',
            default => $name,
        };
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->name)) ?: [];
        $first = mb_substr($parts[0] ?? 'U', 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
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

    public function canDeleteTicketAttachment(TicketAttachment $attachment): bool
    {
        return (int) $attachment->uploaded_by === (int) $this->id
            || $this->isSystemAdministrator()
            || $this->hasPermission('tickets.assign');
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
