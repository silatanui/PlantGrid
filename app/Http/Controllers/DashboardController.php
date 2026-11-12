<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $user = $request->user();

        return view('dashboard.index', [
            'title' => 'Dashboard',
            'cards' => $dashboard->cards($user),
            'quickLinks' => $dashboard->quickLinks($user),
            'notices' => $dashboard->notices($user),
            'charts' => $dashboard->charts($user),
            'activity' => $user->hasPermission('audit.view') ? $dashboard->activity() : collect(),
            'tickets' => $dashboard->assignedTickets($user),
            'recentTickets' => $dashboard->recentTickets($user),
            'deadlines' => $dashboard->deadlines($user),
            'ticketHeading' => $user->hasPermission('tickets.update') ? 'My assigned tickets' : 'My open tickets',
            'maintenance' => $user->hasPermission('maintenance.view') ? $dashboard->upcomingMaintenance() : collect(),
            'alerts' => $user->hasPermission('monitoring.view') ? $dashboard->alerts() : collect(),
            'plantName' => \App\Models\SystemSetting::getValue('plant_name', 'Demonstration plant'),
        ]);
    }
}
