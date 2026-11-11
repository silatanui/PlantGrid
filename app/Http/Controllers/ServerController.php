<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\IpAddress;
use App\Models\Server;
use App\Models\ServerService;
use App\Services\AuditLogger;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    public function index(Request $request)
    {
        $sort = TableSort::from(
            $request,
            ['hostname', 'server_role', 'operating_system', 'status'],
            'hostname',
        );

        return view('servers.index', [
            'title' => 'Servers',
            'servers' => $sort->apply(
                Server::query()->with(['ipAddress', 'services'])
            )->get(),
            'assets' => Asset::query()->orderBy('asset_tag')->get(),
            'addresses' => IpAddress::query()->orderBy('ip_address')->get(),
            'sort' => $sort,
        ]);
    }

    public function show(Server $server)
    {
        $server->load(['asset', 'ipAddress.subnet', 'services']);

        return view('servers.show', [
            'title' => $server->hostname,
            'server' => $server,
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('servers.manage'), 403);
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:80', 'unique:servers,hostname'],
            'operating_system' => ['required', 'string', 'max:80'],
            'os_version' => ['nullable', 'string', 'max:80'],
            'server_role' => ['required', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['development', 'test', 'simulated_production'])],
            'cpu_cores' => ['nullable', 'integer', 'min:1', 'max:256'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'storage_gb' => ['nullable', 'integer', 'min:1'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'ip_address_id' => ['nullable', 'exists:ip_addresses,id'],
            'status' => ['required', Rule::in(['active', 'under_maintenance', 'retired'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $server = Server::query()->create($data);
        $audit->log('server.created', 'server', $server->id, [], ['hostname' => $server->hostname]);

        return redirect()->route('servers.show', $server)->with('status', $server->hostname.' was added. This is a documentation record, not a live server.');
    }

    public function storeService(Request $request, Server $server, AuditLogger $audit)
    {
        abort_unless($request->user()->hasPermission('servers.manage'), 403);
        $data = $request->validate([
            'service_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'protocol' => ['nullable', Rule::in(['tcp', 'udp'])],
            'status' => ['required', Rule::in(['running', 'stopped', 'unknown'])],
        ]);
        $service = $server->services()->create($data);
        $audit->log('server.service_created', 'server_service', $service->id, [], ['server' => $server->hostname]);

        return back()->with('status', 'Service '.$service->service_name.' was recorded.');
    }
}
