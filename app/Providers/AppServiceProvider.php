<?php

namespace App\Providers;

use App\Models\DirectMessage;
use App\Models\MonitoringAlert;
use App\Monitoring\Contracts\MonitoringProvider;
use App\Monitoring\Providers\SimulatedMonitoringProvider;
use App\Services\PresenceService;
use App\Services\ShellPanelService;
use App\Services\WorkspaceTabService;
use App\Support\Navigation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MonitoringProvider::class, SimulatedMonitoringProvider::class);

        if ($this->app->environment('testing')) {
            return;
        }

        $public = config('plantgrid.public_path');

        if (is_string($public) && is_dir($public)) {
            $this->app->usePublicPath($public);
        }
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            if ($user === null) {
                return;
            }

            $user->loadMissing('roles');
            $alerts = $user->hasPermission('monitoring.view')
                ? MonitoringAlert::query()->whereIn('status', ['open', 'acknowledged'])->latest('triggered_at')->limit(5)->get()
                : collect();

            $unreadMessages = DirectMessage::query()
                ->with('sender:id,name')
                ->unreadFor($user)
                ->latest()
                ->limit(5)
                ->get();

            $unreadNotifications = $user->unreadNotifications()->latest()->limit(5)->get();
            $onlineUsers = $user->hasPermission('users.view')
                ? app(PresenceService::class)->onlineUsers()->reject(fn ($person) => $person->id === $user->id)->values()
                : collect();

            $shell = app(ShellPanelService::class);
            $workspace = app(WorkspaceTabService::class);

            $openCasesHref = $user->isEmployeeRequester()
                ? route('helpdesk.index', ['scope' => 'mine', 'status' => 'open'])
                : route('helpdesk.index', ['status' => 'open']);

            $view->with([
                'navigation' => Navigation::groups($user),
                'primaryNavigation' => Navigation::primary($user),
                'headerAlerts' => $alerts,
                'headerAlertCount' => $alerts->count(),
                'headerMessages' => $unreadMessages,
                'headerMessageCount' => DirectMessage::query()->unreadFor($user)->count(),
                'headerNotifications' => $unreadNotifications,
                'headerNotificationCount' => $user->unreadNotifications()->count(),
                'headerOnlineUsers' => $onlineUsers->take(6),
                'headerOnlineCount' => $onlineUsers->count(),
                'plantName' => \App\Models\SystemSetting::getValue('plant_name', 'Northridge Processing'),
                'leftPanel' => $shell->left($user),
                'rightPanel' => $shell->right($user),
                'workspaceTabs' => $workspace->all(),
                'workspaceTabService' => $workspace,
                'openCasesHref' => $openCasesHref,
            ]);
        });
    }
}
