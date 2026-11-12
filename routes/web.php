<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HelpdeskController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LiveInboxController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MentionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\WorkspaceTabController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotCreate'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgotStore'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetCreate'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetStore'])->name('password.update');
    Route::get('/auth/entra/redirect', [AuthController::class, 'entraRedirect'])->name('auth.entra.redirect');
    Route::get('/auth/entra/callback', [AuthController::class, 'entraCallback'])->name('auth.entra.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat'])->name('presence.heartbeat');
    Route::get('/presence/live', [PresenceController::class, 'live'])
        ->middleware('permission:users.view')
        ->name('presence.live');
    Route::get('/inbox/live', [LiveInboxController::class, 'feed'])->name('inbox.live');
    Route::get('/inbox/thread/{person}', [LiveInboxController::class, 'thread'])->name('inbox.thread');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
    Route::get('/mentions/suggest', [MentionController::class, 'suggest'])->name('mentions.suggest');

    Route::get('/people/{person}', [PeopleController::class, 'show'])->name('people.show');
    Route::put('/people/{person}/contact', [PeopleController::class, 'updateContact'])->name('people.contact');
    Route::post('/people/{person}/message', [PeopleController::class, 'message'])->name('people.message');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/workspace/tabs/close', [WorkspaceTabController::class, 'destroy'])->name('workspace.tabs.close');
    Route::post('/workspace/tabs/reorder', [WorkspaceTabController::class, 'reorder'])->name('workspace.tabs.reorder');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
    Route::get('/docs/{slug}', [DocsController::class, 'show'])->name('docs.show');

    Route::prefix('ai')->name('ai.')->group(function () {
        Route::post('/chat', [AiController::class, 'chat'])->name('chat');
        Route::post('/actions/confirm', [AiController::class, 'confirm'])->name('actions.confirm');
        Route::post('/actions/cancel', [AiController::class, 'cancel'])->name('actions.cancel');
        Route::post('/refine', [AiController::class, 'refine'])->name('refine');
        Route::post('/paraphrase', [AiController::class, 'paraphrase'])->name('paraphrase');
        Route::post('/triage/{ticket}', [AiController::class, 'triage'])
            ->middleware('permission:tickets.assign')
            ->name('triage');
        Route::post('/triage/{ticket}/apply', [AiController::class, 'applyTriage'])
            ->middleware('permission:tickets.assign')
            ->name('triage.apply');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::middleware('permission:assets.view')->group(function () {
        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/export', [AssetController::class, 'export'])->name('assets.export');
        Route::get('/assets/create', [AssetController::class, 'create'])->middleware('permission:assets.create')->name('assets.create');
        Route::post('/assets', [AssetController::class, 'store'])->middleware('permission:assets.create')->name('assets.store');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->middleware('permission:assets.update')->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->middleware('permission:assets.update')->name('assets.update');
        Route::post('/assets/{asset}/retire', [AssetController::class, 'retire'])->middleware('permission:assets.retire')->name('assets.retire');
        Route::post('/assets/{asset}/assign', [AssetController::class, 'assign'])->middleware('permission:assets.update')->name('assets.assign');
        Route::post('/assets/{asset}/return', [AssetController::class, 'returnAssignment'])->middleware('permission:assets.update')->name('assets.return');
    });

    // Detail is authorised in the controller so case partners can open a linked
    // device without Assets register permission. Keep after /assets/create.
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');

    Route::middleware('permission:network.view')->group(function () {
        Route::get('/network', [NetworkController::class, 'index'])->name('network.index');
        Route::get('/network/devices/{device}', [NetworkController::class, 'show'])->name('network.show');
        Route::post('/network/devices', [NetworkController::class, 'storeDevice'])->name('network.devices.store');
        Route::post('/network/subnets', [NetworkController::class, 'storeSubnet'])->name('network.subnets.store');
        Route::post('/network/vlans', [NetworkController::class, 'storeVlan'])->name('network.vlans.store');
        Route::post('/network/ip-addresses', [NetworkController::class, 'storeIp'])->name('network.ips.store');
        Route::post('/network/links', [NetworkController::class, 'storeLink'])->name('network.links.store');
    });

    Route::middleware('permission:servers.view')->group(function () {
        Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
        Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');
        Route::get('/servers/{server}', [ServerController::class, 'show'])->name('servers.show');
        Route::post('/servers/{server}/services', [ServerController::class, 'storeService'])->name('servers.services.store');
    });

    Route::middleware('permission:tickets.view.own,tickets.view.department,tickets.view.all')->group(function () {
        Route::get('/helpdesk', [HelpdeskController::class, 'index'])->name('helpdesk.index');
        Route::get('/helpdesk/my-tickets', function () {
            return redirect()->route('helpdesk.index', array_filter([
                'scope' => 'mine',
                'status' => request('status'),
                'priority' => request('priority'),
                'q' => request('q'),
            ], fn ($value) => $value !== null && $value !== ''));
        })->name('helpdesk.mine');
        Route::get('/helpdesk/assigned', function () {
            return redirect()->route('helpdesk.index', array_filter([
                'scope' => 'assigned',
                'status' => request('status'),
                'priority' => request('priority'),
                'q' => request('q'),
            ], fn ($value) => $value !== null && $value !== ''));
        })->name('helpdesk.assigned');
        Route::get('/helpdesk/create', [HelpdeskController::class, 'create'])->middleware('permission:tickets.create')->name('helpdesk.create');
        Route::post('/helpdesk', [HelpdeskController::class, 'store'])->middleware('permission:tickets.create')->name('helpdesk.store');
        Route::get('/helpdesk/{ticket}', [HelpdeskController::class, 'show'])->name('helpdesk.show');
        Route::post('/helpdesk/{ticket}/take-ownership', [HelpdeskController::class, 'takeOwnership'])->name('helpdesk.take-ownership');
        Route::post('/helpdesk/{ticket}/transfer', [HelpdeskController::class, 'transfer'])->name('helpdesk.transfer');
        Route::post('/helpdesk/{ticket}/transition', [HelpdeskController::class, 'transition'])->name('helpdesk.transition');
        Route::post('/helpdesk/{ticket}/partners', [HelpdeskController::class, 'addPartner'])
            ->name('helpdesk.partners.store');
        Route::delete('/helpdesk/{ticket}/partners/{partner}', [HelpdeskController::class, 'removePartner'])
            ->name('helpdesk.partners.destroy');
        Route::post('/helpdesk/{ticket}/comments', [HelpdeskController::class, 'comment'])->name('helpdesk.comment');
        Route::put('/helpdesk/{ticket}/comments/{comment}', [HelpdeskController::class, 'updateComment'])->name('helpdesk.comment.update');
        Route::delete('/helpdesk/{ticket}/comments/{comment}', [HelpdeskController::class, 'deleteComment'])->name('helpdesk.comment.destroy');
        Route::get('/helpdesk/{ticket}/attachments/{attachment}', [HelpdeskController::class, 'download'])->name('helpdesk.attachment');
        Route::get('/helpdesk/{ticket}/attachments/{attachment}/preview', [HelpdeskController::class, 'preview'])->name('helpdesk.attachment.preview');
        Route::delete('/helpdesk/{ticket}/attachments/{attachment}', [HelpdeskController::class, 'destroyAttachment'])->name('helpdesk.attachment.destroy');
    });

    Route::middleware('permission:maintenance.view')->group(function () {
        Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
        Route::post('/maintenance/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');
    });

    Route::middleware('permission:production.view')->group(function () {
        Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
        Route::get('/production/systems/{system}', [ProductionController::class, 'show'])->name('production.show');
        Route::post('/production/systems', [ProductionController::class, 'storeSystem'])->name('production.systems.store');
        Route::post('/production/equipment', [ProductionController::class, 'storeEquipment'])->name('production.equipment.store');
        Route::post('/production/incidents', [ProductionController::class, 'storeIncident'])->name('production.incidents.store');
    });

    Route::middleware('permission:security.view')->group(function () {
        Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
        Route::post('/security/incidents', [SecurityController::class, 'storeIncident'])->name('security.incidents.store');
        Route::post('/security/incidents/{incident}', [SecurityController::class, 'updateIncident'])->name('security.incidents.update');
        Route::post('/security/checks', [SecurityController::class, 'storeCheck'])->name('security.checks.store');
    });

    Route::middleware('permission:backups.view')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/plans', [BackupController::class, 'storePlan'])->name('backups.plans.store');
        Route::post('/backups/runs', [BackupController::class, 'storeRun'])->name('backups.runs.store');
        Route::post('/backups/recovery-tests', [BackupController::class, 'storeTest'])->name('backups.tests.store');
    });

    Route::middleware('permission:monitoring.view')->group(function () {
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::post('/monitoring/scenario', [MonitoringController::class, 'scenario'])->name('monitoring.scenario');
        Route::post('/monitoring/collect', [MonitoringController::class, 'collect'])->name('monitoring.collect');
        Route::post('/monitoring/alerts/{alert}/acknowledge', [MonitoringController::class, 'acknowledge'])->name('monitoring.acknowledge');
        Route::post('/monitoring/alerts/{alert}/resolve', [MonitoringController::class, 'resolve'])->name('monitoring.resolve');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
        Route::get('/performance/export', [PerformanceController::class, 'export'])->name('performance.export');
    });

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/administration/users', [AdminController::class, 'index'])->name('admin.index');
        Route::get('/administration/online', [AdminController::class, 'online'])->name('admin.online');
        Route::post('/administration/online/{user}/assign', [AdminController::class, 'assignOnline'])
            ->middleware('permission:tickets.assign')
            ->name('admin.online.assign');
        Route::post('/administration/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::post('/administration/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::post('/administration/roles/{role}', [AdminController::class, 'updateRole'])->name('admin.roles.update');
        Route::post('/administration/departments', [AdminController::class, 'storeDepartment'])->name('admin.departments.store');
        Route::post('/administration/locations', [AdminController::class, 'storeLocation'])->name('admin.locations.store');
        Route::post('/administration/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
        Route::post('/administration/identity', [AdminController::class, 'updateIdentity'])->name('admin.identity.update');
    });

    Route::get('/audit-logs', [AuditController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');
});
