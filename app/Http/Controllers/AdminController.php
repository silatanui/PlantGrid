<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdentityService;
use App\Services\PresenceService;
use App\Services\TicketService;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $sort = TableSort::from(
            $request,
            ['name', 'email', 'status'],
            'name',
        );

        $users = $sort->apply(
            User::query()->with(['roles', 'department'])
        )->get();

        return view('admin.index', [
            'title' => 'Administration',
            'users' => $users,
            'onlineMap' => app(PresenceService::class)->onlineMap($users),
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'locations' => Location::query()->with('department')->orderBy('name')->get(),
            'plantName' => SystemSetting::getValue('plant_name', 'Northridge Processing'),
            'identity' => app(IdentityService::class)->adminState(),
            'sort' => $sort,
        ]);
    }

    public function online(Request $request, PresenceService $presence)
    {
        abort_unless($request->user()->hasPermission('users.view'), 403);

        $online = $presence->onlineUsers();
        $assignableCases = $request->user()->hasPermission('tickets.assign')
            ? SupportTicket::query()
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderByDesc('reported_at')
                ->limit(40)
                ->get(['id', 'ticket_number', 'subject', 'status', 'assigned_to', 'priority'])
            : collect();

        return view('admin.online', [
            'title' => 'Online users',
            'online' => $online,
            'assignableCases' => $assignableCases,
            'canAssign' => $request->user()->hasPermission('tickets.assign'),
            'onlineWindowMinutes' => PresenceService::ONLINE_MINUTES,
        ]);
    }

    public function assignOnline(Request $request, User $user, TicketService $tickets)
    {
        abort_unless($request->user()->hasPermission('tickets.assign'), 403);
        abort_unless($user->status === 'active', 404);

        $data = $request->validate([
            'ticket_id' => ['required', 'exists:support_tickets,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket = SupportTicket::query()->findOrFail($data['ticket_id']);
        $tickets->assignTo($ticket, $user, $request->user(), $data['note'] ?? null);

        return back()->with('status', $ticket->ticket_number.' was assigned to '.$user->name.'.');
    }

    public function storeUser(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('users.create'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'department_id' => $data['department_id'] ?? null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->roles()->sync([$data['role_id']]);
        $audit->log('user.created', 'user', $user->id, [], ['email' => $user->email, 'role_id' => $data['role_id']]);

        return back()->with('status', $user->name.' can now sign in.');
    }

    public function updateUser(Request $request, User $user, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        if ($user->id === $request->user()->id && $data['status'] !== 'active') {
            return back()->withErrors(['status' => 'You cannot deactivate the account you are using.']);
        }

        $user->update(['status' => $data['status']]);
        $user->roles()->sync([$data['role_id']]);
        $audit->log('user.updated', 'user', $user->id, [], ['status' => $user->status, 'role_id' => $data['role_id']]);

        return back()->with('status', 'User access updated.');
    }

    public function updateRole(Request $request, Role $role, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);
        if ($role->name === 'System Administrator') {
            return back()->withErrors(['role' => 'The System Administrator role stays fully granted so the platform cannot be locked.']);
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);
        $audit->log('role.updated', 'role', $role->id, [], ['permissions' => $data['permissions'] ?? []]);

        return back()->with('status', $role->name.' permissions were updated.');
    }

    public function storeDepartment(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:12', 'alpha_dash', 'unique:departments,code'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['status'] = 'active';
        $department = Department::query()->create($data);
        $audit->log('department.created', 'department', $department->id, [], ['code' => $department->code]);

        return back()->with('status', 'Department '.$department->code.' was added.');
    }

    public function storeLocation(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:16', 'alpha_dash', 'unique:locations,code'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['status'] = 'active';
        $location = Location::query()->create($data);
        $audit->log('location.created', 'location', $location->id, [], ['code' => $location->code]);

        return back()->with('status', 'Location '.$location->name.' was added.');
    }

    public function updateSettings(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);
        $data = $request->validate([
            'plant_name' => ['required', 'string', 'max:160'],
        ]);
        SystemSetting::putValue('plant_name', $data['plant_name'], $request->user()->id, 'Fictional plant display name');
        $audit->log('settings.updated', 'system_setting', null, [], ['plant_name' => $data['plant_name']]);

        return back()->with('status', 'Plant display name saved.');
    }

    public function updateIdentity(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);
        $data = $request->validate([
            'sso_entra_enabled' => ['nullable', 'boolean'],
            'sso_entra_tenant' => ['nullable', 'string', 'max:120'],
            'sso_entra_client_id' => ['nullable', 'string', 'max:120'],
            'sso_entra_client_secret' => ['nullable', 'string', 'max:255'],
        ]);

        SystemSetting::putValue('sso_entra_enabled', $request->boolean('sso_entra_enabled') ? '1' : '0', $request->user()->id, 'Entra SSO enabled');
        SystemSetting::putValue('sso_entra_tenant', trim((string) ($data['sso_entra_tenant'] ?? '')), $request->user()->id, 'Entra tenant');
        SystemSetting::putValue('sso_entra_client_id', trim((string) ($data['sso_entra_client_id'] ?? '')), $request->user()->id, 'Entra client id');
        if (array_key_exists('sso_entra_client_secret', $data) && filled($data['sso_entra_client_secret'])) {
            SystemSetting::putValue('sso_entra_client_secret', trim((string) $data['sso_entra_client_secret']), $request->user()->id, 'Entra client secret');
        }

        $audit->log('settings.identity', 'system_setting', null, [], [
            'sso_entra_enabled' => $request->boolean('sso_entra_enabled'),
            'sso_entra_tenant' => $data['sso_entra_tenant'] ?? null,
        ]);

        return back()->with('status', 'Identity settings saved.');
    }
}
