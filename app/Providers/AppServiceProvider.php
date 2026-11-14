<?php

namespace App\Providers;

use App\Models\DirectMessage;
use App\Models\MonitoringAlert;
use App\Monitoring\Contracts\MonitoringProvider;
use App\Monitoring\Providers\SimulatedMonitoringProvider;
use App\Services\BrandingService;
use App\Services\NavigationHistory;
use App\Services\PresenceService;
use App\Services\ShellPanelService;
use App\Services\WorkspaceTabService;
use App\Support\NavBadges;
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
            $navHistory = app(NavigationHistory::class);
            $navBadges = NavBadges::for($user);

            $openCasesHref = $user->isEmployeeRequester()
                ? route('helpdesk.index', ['scope' => 'mine', 'status' => 'open'])
                : route('helpdesk.index', ['status' => 'open']);

            $branding = app(BrandingService::class)->forViews();

            $view->with(array_merge($branding, [
                'branding' => $branding,
                'navigation' => Navigation::groups($user),
                'primaryNavigation' => Navigation::primary($user),
                'navBadges' => $navBadges,
                'headerAlerts' => $alerts,
                'headerAlertCount' => $alerts->count(),
                'headerMessages' => $unreadMessages,
                'headerMessageCount' => $navBadges['messages'],
                'headerNotifications' => $unreadNotifications,
                'headerNotificationCount' => $navBadges['notifications'],
                'monitoringStatus' => $navBadges['monitoring_status'],
                'headerOnlineUsers' => $onlineUsers->take(6),
                'headerOnlineCount' => $onlineUsers->count(),
                'leftPanel' => $shell->left($user),
                'rightPanel' => $shell->right($user),
                'workspaceTabs' => $workspace->all(),
                'workspaceTabService' => $workspace,
                'navPrevious' => $navHistory->previous(request()),
                'openCasesHref' => $openCasesHref,
            ]));
        });

        View::composer(['home', 'auth.login', 'layouts.guest'], function ($view) {
            $branding = app(BrandingService::class)->forViews();
            $view->with(array_merge($branding, ['branding' => $branding]));
        });
    }
}
