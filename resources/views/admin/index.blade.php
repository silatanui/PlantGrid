@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
        <div>
            <h1 class="pg-page-title mb-1">Administration</h1>
            <p class="pg-page-lead mb-0">Users, roles, departments and the fictional plant name.</p>
        </div>
        <a class="btn btn-pg" href="{{ route('admin.online') }}">Online users</a>
    </div>

    <section class="pg-card my-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Users</h2></div>
        <table class="pg-table">
            <thead>
                <tr>
                    <x-sort-th column="name" label="Name" :sort="$sort" route-name="admin.index" />
                    <x-sort-th column="email" label="Email" :sort="$sort" route-name="admin.index" />
                    <th>Department</th>
                    <th>Role</th>
                    <th>Presence</th>
                    <x-sort-th column="status" label="Status" :sort="$sort" route-name="admin.index" />
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($users as $person)
                <tr>
                    <td>{{ $person->name }}</td>
                    <td class="pg-mono">{{ $person->email }}</td>
                    <td>{{ $person->department->code ?? '-' }}</td>
                    <td>{{ $person->roles->pluck('name')->join(', ') }}</td>
                    <td>
                        @if (! empty($onlineMap[$person->id]))
                            <span class="pg-online-dot" aria-hidden="true"></span> Online
                        @else
                            <span class="text-muted">Offline</span>
                        @endif
                    </td>
                    <td>{!! \App\Support\Labels::badge($person->status) !!}</td>
                    <td>
                        @if (auth()->user()->hasPermission('users.update'))
                            <form class="d-flex gap-1" method="post" action="{{ route('admin.users.update', $person) }}">
                                @csrf
                                <select class="form-select form-select-sm" name="role_id">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected($person->roles->contains('id', $role->id))>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <select class="form-select form-select-sm" name="status">
                                    <option value="active" @selected($person->status === 'active')>Active</option>
                                    <option value="inactive" @selected($person->status === 'inactive')>Inactive</option>
                                </select>
                                <button class="btn btn-sm btn-pg-line" type="submit">Save</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    @if (auth()->user()->hasPermission('users.create'))
        <form class="pg-card pg-card-pad mb-3" method="post" action="{{ route('admin.users.store') }}">
            @csrf
            <h2 class="pg-section-title">Add a user</h2>
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" required></div>
                <div class="col-md-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" required></div>
                <div class="col-md-2"><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" required></div>
                <div class="col-md-2"><label class="form-label" for="department_id">Department</label>
                    <select class="form-select" id="department_id" name="department_id"><option value="">None</option>@foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->code }}</option>@endforeach</select>
                </div>
                <div class="col-md-2"><label class="form-label" for="role_id">Role</label>
                    <select class="form-select" id="role_id" name="role_id">@foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select>
                </div>
            </div>
            <button class="btn btn-pg mt-3" type="submit">Create user</button>
        </form>
    @endif

    @if (auth()->user()->hasPermission('roles.manage'))
        <section class="pg-card pg-card-pad mb-3">
            <h2 class="pg-section-title">Role permissions</h2>
            <p class="text-muted">The System Administrator role stays fully granted.</p>
            @foreach ($roles as $role)
                <form class="pg-card pg-card-pad mt-2" method="post" action="{{ route('admin.roles.update', $role) }}">
                    @csrf
                    <h3 class="h6">{{ $role->name }}</h3>
                    <div class="row">
                        @foreach ($permissions as $permission)
                            <div class="col-md-4">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id)) @disabled($role->name === 'System Administrator')>
                                    <span class="form-check-label pg-mono">{{ $permission->name }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @if ($role->name !== 'System Administrator')
                        <button class="btn btn-sm btn-pg mt-2" type="submit">Save {{ $role->name }}</button>
                    @endif
                </form>
            @endforeach
        </section>
        <form class="pg-card pg-card-pad mb-3" method="post" action="{{ route('admin.settings.update') }}">
            @csrf
            <h2 class="pg-section-title">Plant display name</h2>
            <label class="form-label" for="plant_name">Name shown in the sidebar</label>
            <input class="form-control" id="plant_name" name="plant_name" value="{{ $plantName }}" required>
            <button class="btn btn-pg mt-3" type="submit">Save name</button>
        </form>
        <form class="pg-card pg-card-pad mb-3" method="post" action="{{ route('admin.identity.update') }}">
            @csrf
            <h2 class="pg-section-title">Identity · Microsoft Entra ID</h2>
            <p class="text-muted small">SSO maps an Entra email to an existing active PlantGrid user. It does not auto-create accounts.</p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="1" id="sso_entra_enabled" name="sso_entra_enabled" @checked($identity['enabled'] ?? false)>
                <label class="form-check-label" for="sso_entra_enabled">Enable Sign in with Microsoft</label>
            </div>
            <label class="form-label" for="sso_entra_tenant">Tenant ID</label>
            <input class="form-control mb-2" id="sso_entra_tenant" name="sso_entra_tenant" value="{{ $identity['tenant'] ?? '' }}" placeholder="Directory (tenant) ID">
            <label class="form-label" for="sso_entra_client_id">Application (client) ID</label>
            <input class="form-control mb-2" id="sso_entra_client_id" name="sso_entra_client_id" value="{{ $identity['client_id'] ?? '' }}">
            <label class="form-label" for="sso_entra_client_secret">Client secret</label>
            <input class="form-control mb-2" id="sso_entra_client_secret" name="sso_entra_client_secret" type="password" placeholder="{{ filled($identity['client_secret'] ?? null) ? 'Leave blank to keep current secret' : 'Client secret value' }}">
            <p class="text-muted small mb-0">Redirect URI to register in Entra: <span class="pg-mono">{{ $identity['redirect'] ?? '' }}</span></p>
            <button class="btn btn-pg mt-3" type="submit">Save identity settings</button>
        </form>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <section class="pg-card">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Departments</h2></div>
                <table class="pg-table"><tbody>@foreach ($departments as $department)<tr><td>{{ $department->name }}</td><td class="pg-mono">{{ $department->code }}</td></tr>@endforeach</tbody></table>
            </section>
        </div>
        <div class="col-md-6">
            <section class="pg-card">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Locations</h2></div>
                <table class="pg-table"><tbody>@foreach ($locations as $location)<tr><td>{{ $location->name }}</td><td class="pg-mono">{{ $location->code }}</td></tr>@endforeach</tbody></table>
            </section>
        </div>
    </div>
@endsection
