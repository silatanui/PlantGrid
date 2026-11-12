<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetMaintenanceRecord;
use App\Models\AssetStatusHistory;
use App\Models\AuditLog;
use App\Models\BackupPlan;
use App\Models\BackupRun;
use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\IpAddress;
use App\Models\Location;
use App\Models\MaintenanceRecord;
use App\Models\MonitoringMetric;
use App\Models\NetworkDevice;
use App\Models\NetworkLink;
use App\Models\NetworkSubnet;
use App\Models\Permission;
use App\Models\ProductionEquipment;
use App\Models\ProductionIncident;
use App\Models\ProductionSystem;
use App\Models\RecoveryTest;
use App\Models\Role;
use App\Models\SecurityControl;
use App\Models\SecurityIncident;
use App\Models\Server;
use App\Models\ServerService;
use App\Models\SupportTicket;
use App\Models\SystemApplication;
use App\Models\SystemSetting;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Vlan;
use App\Monitoring\Contracts\MonitoringProvider;
use App\Monitoring\MetricCollectionService;
use App\Support\DemoContact;
use App\Support\DeviceMedia;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->permissions();
        $roles = $this->roles($permissions);
        [$departments, $locations] = $this->organisation();
        $users = $this->users($departments, $roles);
        $this->call(TeamMembersSeeder::class);
        $this->call(UserContactSeeder::class);
        $categories = $this->categories();
        $vendors = $this->vendors();
        $assets = $this->assets($categories, $vendors, $departments, $locations, $users);
        $network = $this->network($assets, $locations);
        $servers = $this->servers($assets, $network['addresses']);
        $applications = $this->applications($vendors, $departments);
        $tickets = $this->tickets($users, $departments, $assets, $applications);
        $this->call(MoreCasesSeeder::class);
        $systems = $this->production($departments, $vendors, $users, $assets, $locations, $tickets);
        $this->maintenance($users, $vendors, $assets, $servers, $systems['equipment']);
        $this->security($users);
        $this->backups($users, $servers, $applications);
        $this->monitoring($servers, $network['devices']);
        $this->settings($users['admin']);
        $this->activity($users, $tickets['printer']);
    }

    private function permissions(): array
    {
        $names = [
            'dashboard.view', 'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.manage', 'assets.view', 'assets.create', 'assets.update', 'assets.retire',
            'network.view', 'network.manage', 'servers.view', 'servers.manage',
            'tickets.view.own', 'tickets.view.department', 'tickets.view.all', 'tickets.create',
            'tickets.assign', 'tickets.update', 'tickets.resolve', 'tickets.close', 'tickets.reopen',
            'maintenance.view', 'maintenance.manage', 'production.view', 'production.manage',
            'security.view', 'security.manage', 'backups.view', 'backups.manage',
            'monitoring.view', 'monitoring.manage', 'alerts.acknowledge',
            'reports.view', 'reports.export', 'audit.view',
        ];

        $records = [];
        foreach ($names as $name) {
            $records[$name] = Permission::query()->create([
                'name' => $name,
                'description' => str_replace('.', ' ', $name),
            ]);
        }

        return $records;
    }

    private function roles(array $permissions): array
    {
        $definitions = [
            'System Administrator' => array_keys($permissions),
            'ICT Manager' => [
                'dashboard.view', 'assets.view', 'assets.create', 'assets.update', 'assets.retire',
                'network.view', 'network.manage', 'servers.view', 'servers.manage',
                'tickets.view.all', 'tickets.create', 'tickets.assign', 'tickets.update', 'tickets.resolve', 'tickets.close', 'tickets.reopen',
                'maintenance.view', 'maintenance.manage', 'production.view', 'production.manage',
                'security.view', 'security.manage', 'backups.view', 'backups.manage',
                'monitoring.view', 'monitoring.manage', 'alerts.acknowledge', 'reports.view', 'reports.export', 'audit.view',
            ],
            'ICT Technician' => [
                'dashboard.view', 'assets.view', 'assets.update', 'network.view', 'servers.view',
                'tickets.view.all', 'tickets.update', 'tickets.resolve', 'tickets.close', 'tickets.reopen',
                'maintenance.view', 'maintenance.manage', 'production.view', 'monitoring.view', 'alerts.acknowledge', 'reports.view',
            ],
            'Department Manager' => [
                'dashboard.view', 'tickets.view.department', 'tickets.create', 'assets.view',
                'production.view', 'maintenance.view', 'reports.view',
            ],
            'General Employee' => ['dashboard.view', 'tickets.view.own', 'tickets.create'],
            'Auditor' => [
                'dashboard.view', 'assets.view', 'network.view', 'servers.view', 'tickets.view.all',
                'maintenance.view', 'production.view', 'security.view', 'backups.view', 'monitoring.view',
                'reports.view', 'reports.export', 'audit.view',
            ],
        ];

        $roles = [];
        foreach ($definitions as $name => $granted) {
            $role = Role::query()->create([
                'name' => $name,
                'description' => $name.' access for the fictional Northridge demonstration.',
            ]);
            $role->permissions()->sync(collect($granted)->map(fn ($key) => $permissions[$key]->id)->all());
            $roles[$name] = $role;
        }

        return $roles;
    }

    private function organisation(): array
    {
        $departments = collect([
            ['ICT', 'ICT', 'Information and communications technology for the fictional plant.'],
            ['Production', 'PRD', 'Processing and packaging operations.'],
            ['Quality Assurance', 'QA', 'Laboratory and quality checks.'],
            ['Warehouse', 'WHS', 'Finished goods and intake stores.'],
            ['Maintenance', 'MNT', 'Mechanical and facilities maintenance.'],
            ['Finance', 'FIN', 'Administration and finance.'],
        ])->mapWithKeys(function ($row) {
            $department = Department::query()->create([
                'name' => $row[0], 'code' => $row[1], 'description' => $row[2], 'status' => 'active',
            ]);

            return [$row[1] => $department];
        });

        $locations = collect([
            ['Server Room', 'SRV-RM', 'ICT', 'Controlled room for demonstration servers.'],
            ['ICT Office', 'ICT-OF', 'ICT', 'ICT support office.'],
            ['Packaging Hall', 'PKG-HL', 'PRD', 'Fictional packaging area.'],
            ['Processing Hall', 'PRC-HL', 'PRD', 'Fictional processing area.'],
            ['QA Laboratory', 'QA-LAB', 'QA', 'Quality laboratory.'],
            ['Warehouse Office', 'WHS-OF', 'WHS', 'Warehouse supervision desk.'],
            ['Administration Block', 'ADM-BL', 'FIN', 'Office block.'],
            ['Main Entrance', 'GATE', 'ICT', 'Entrance used for the CCTV record.'],
        ])->mapWithKeys(function ($row) use ($departments) {
            $location = Location::query()->create([
                'name' => $row[0],
                'code' => $row[1],
                'department_id' => $departments[$row[2]]->id,
                'description' => $row[3],
                'status' => 'active',
            ]);

            return [$row[1] => $location];
        });

        return [$departments, $locations];
    }

    private function users($departments, array $roles): array
    {
        $password = 'PlantGrid-Demo';
        $people = [
            'admin' => ['Amina Okello', 'amina.okello@northridge.example', 'ICT', 'System Administrator'],
            'manager' => ['Daniel Mwangi', 'daniel.mwangi@northridge.example', 'ICT', 'ICT Manager'],
            'technician' => ['Faith Cheruiyot', 'faith.cheruiyot@northridge.example', 'ICT', 'ICT Technician'],
            'productionManager' => ['Peter Kamau', 'peter.kamau@northridge.example', 'PRD', 'Department Manager'],
            'employee' => ['Grace Wanjiku', 'grace.wanjiku@northridge.example', 'PRD', 'General Employee'],
            'auditor' => ['Helen Achieng', 'helen.achieng@northridge.example', 'ICT', 'Auditor'],
        ];

        $users = [];
        foreach ($people as $key => [$name, $email, $department, $role]) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'department_id' => $departments[$department]->id,
                'status' => 'active',
                'email_verified_at' => now(),
                ...DemoContact::for($email, $department, $role),
            ]);
            $user->roles()->sync([$roles[$role]->id]);
            $users[$key] = $user;
        }

        return $users;
    }

    private function categories()
    {
        return collect([
            'Computers', 'Servers and storage', 'Network equipment', 'Printers and scanners',
            'Barcode scanners', 'Weighing scales', 'CCTV and access devices', 'Monitors and peripherals',
        ])->mapWithKeys(fn ($name) => [
            $name => AssetCategory::query()->create(['name' => $name, 'description' => $name.' used in the fictional plant register.']),
        ]);
    }

    private function vendors()
    {
        return collect([
            ['Helio Office Supply', 'Wanjiku Helio', 'sales@helio-supply.example'],
            ['Nimbus Network Co.', 'Brian Otieno', 'support@nimbus-network.example'],
            ['Kite Label Systems', 'Asha Noor', 'desk@kitelabel.example'],
            ['Savannah Scale Works', 'Tom Ruto', 'service@savannah-scale.example'],
        ])->mapWithKeys(function ($row) {
            $vendor = Vendor::query()->create([
                'name' => $row[0],
                'contact_person' => $row[1],
                'email' => $row[2],
                'phone' => '+254700000000',
                'address' => 'Fictional supplier, demonstration only',
                'support_notes' => 'Fictional vendor. Not a real company.',
            ]);

            return [$row[0] => $vendor];
        });
    }

    private function assets($categories, $vendors, $departments, $locations, array $users): array
    {
        $rows = [
            ['PG-PC-0101', 'Packaging supervisor laptop', 'Computers', 'Helio Office Supply', 'Lenovo', 'ThinkBook 14', 'SN-PC-0101', 'PRD', 'PKG-HL', 'active', 'good', $users['employee']->id],
            ['PG-PC-0102', 'ICT technician laptop', 'Computers', 'Helio Office Supply', 'Dell', 'Latitude 5440', 'SN-PC-0102', 'ICT', 'ICT-OF', 'active', 'good', $users['technician']->id],
            ['PG-PC-0103', 'QA laboratory desktop', 'Computers', 'Helio Office Supply', 'HP', 'ProDesk 400', 'SN-PC-0103', 'QA', 'QA-LAB', 'active', 'fair', null],
            ['PG-PC-0104', 'Warehouse desk computer', 'Computers', 'Helio Office Supply', 'Dell', 'OptiPlex 7010', 'SN-PC-0104', 'WHS', 'WHS-OF', 'active', 'good', null],
            ['PG-PC-0090', 'Retired intake desktop', 'Computers', 'Helio Office Supply', 'HP', 'Compaq 6200', 'SN-PC-0090', 'PRD', 'PRC-HL', 'retired', 'poor', null],
            ['PG-SRV-0001', 'Directory services server', 'Servers and storage', 'Nimbus Network Co.', 'Dell', 'PowerEdge T350', 'SN-SRV-0001', 'ICT', 'SRV-RM', 'active', 'good', null],
            ['PG-SRV-0002', 'File services server', 'Servers and storage', 'Nimbus Network Co.', 'Dell', 'PowerEdge T350', 'SN-SRV-0002', 'ICT', 'SRV-RM', 'active', 'fair', null],
            ['PG-SRV-0003', 'Application server', 'Servers and storage', 'Nimbus Network Co.', 'HPE', 'ProLiant DL380', 'SN-SRV-0003', 'ICT', 'SRV-RM', 'active', 'good', null],
            ['PG-SRV-0004', 'Database server', 'Servers and storage', 'Nimbus Network Co.', 'HPE', 'ProLiant DL380', 'SN-SRV-0004', 'ICT', 'SRV-RM', 'active', 'good', null],
            ['PG-NET-0001', 'Perimeter firewall', 'Network equipment', 'Nimbus Network Co.', 'Fortinet', 'FortiGate 80F', 'SN-NET-0001', 'ICT', 'SRV-RM', 'active', 'good', null],
            ['PG-NET-0002', 'Core switch', 'Network equipment', 'Nimbus Network Co.', 'Cisco', 'CBS350-24', 'SN-NET-0002', 'ICT', 'SRV-RM', 'active', 'good', null],
            ['PG-NET-0003', 'Processing floor switch', 'Network equipment', 'Nimbus Network Co.', 'Cisco', 'CBS250-24', 'SN-NET-0003', 'ICT', 'PRC-HL', 'active', 'good', null],
            ['PG-NET-0004', 'Packaging floor switch', 'Network equipment', 'Nimbus Network Co.', 'Cisco', 'CBS250-24', 'SN-NET-0004', 'ICT', 'PKG-HL', 'active', 'good', null],
            ['PG-NET-0005', 'Office access switch', 'Network equipment', 'Nimbus Network Co.', 'Cisco', 'CBS220-24', 'SN-NET-0005', 'ICT', 'ADM-BL', 'active', 'good', null],
            ['PG-NET-0006', 'Packaging wireless access point', 'Network equipment', 'Nimbus Network Co.', 'Ubiquiti', 'U6+', 'SN-NET-0006', 'ICT', 'PKG-HL', 'active', 'good', null],
            ['PG-LBL-0142', 'Barcode label printer', 'Printers and scanners', 'Kite Label Systems', 'Zebra', 'ZT411', 'SN-LBL-0142', 'PRD', 'PKG-HL', 'under_maintenance', 'poor', null],
            ['PG-SCN-0201', 'Handheld barcode scanner', 'Barcode scanners', 'Kite Label Systems', 'Zebra', 'DS2208', 'SN-SCN-0201', 'PRD', 'PKG-HL', 'active', 'good', $users['employee']->id],
            ['PG-SCL-0301', 'Intake bench scale', 'Weighing scales', 'Savannah Scale Works', 'Avery', 'ZM510', 'SN-SCL-0301', 'PRD', 'PRC-HL', 'active', 'good', null],
            ['PG-PRT-0401', 'QA laboratory printer', 'Printers and scanners', 'Helio Office Supply', 'HP', 'LaserJet Pro', 'SN-PRT-0401', 'QA', 'QA-LAB', 'active', 'fair', null],
            ['PG-CCTV-0501', 'Entrance camera', 'CCTV and access devices', 'Nimbus Network Co.', 'Hikvision', 'DS-2CD', 'SN-CCTV-0501', 'ICT', 'GATE', 'active', 'good', null],
        ];

        $assets = [];
        foreach ($rows as $row) {
            $media = DeviceMedia::profile($row[4], $row[5], $row[2], $row[1]);
            $asset = Asset::query()->create([
                'asset_tag' => $row[0],
                'name' => $row[1],
                'asset_category_id' => $categories[$row[2]]->id,
                'vendor_id' => $vendors[$row[3]]->id,
                'manufacturer' => $row[4],
                'model' => $row[5],
                'serial_number' => $row[6],
                'department_id' => $departments[$row[7]]->id,
                'location_id' => $locations[$row[8]]->id,
                'purchase_date' => '2024-03-12',
                'warranty_expiry' => $row[9] === 'retired' ? '2025-03-12' : '2027-03-12',
                'condition' => $row[10],
                'status' => $row[9],
                'specifications' => array_merge(
                    ['image_key' => $media['key'], 'summary' => $media['summary']],
                    $media['specs']
                ),
                'notes' => 'Fictional demonstration record for Northridge Processing.',
                'created_by' => $users['admin']->id,
            ]);
            AssetStatusHistory::query()->create([
                'asset_id' => $asset->id,
                'previous_status' => null,
                'new_status' => $asset->status,
                'changed_by' => $users['admin']->id,
                'changed_at' => now()->subMonths(6),
                'notes' => 'Opening balance for the demonstration register.',
            ]);
            if ($row[11]) {
                AssetAssignment::query()->create([
                    'asset_id' => $asset->id,
                    'user_id' => $row[11],
                    'department_id' => $asset->department_id,
                    'assigned_by' => $users['manager']->id,
                    'assigned_at' => now()->subMonths(2),
                ]);
            }
            $assets[$row[0]] = $asset;
        }

        return $assets;
    }

    private function network(array $assets, $locations): array
    {
        $subnets = [
            'servers' => NetworkSubnet::query()->create(['name' => 'Server VLAN', 'network_address' => '10.10.10.0', 'cidr' => 24, 'gateway' => '10.10.10.1', 'description' => 'Fictional server subnet.']),
            'plant' => NetworkSubnet::query()->create(['name' => 'Plant floor', 'network_address' => '10.10.20.0', 'cidr' => 24, 'gateway' => '10.10.20.1', 'description' => 'Fictional production floor subnet.']),
            'office' => NetworkSubnet::query()->create(['name' => 'Office', 'network_address' => '10.10.30.0', 'cidr' => 24, 'gateway' => '10.10.30.1', 'description' => 'Fictional office subnet.']),
            'mgmt' => NetworkSubnet::query()->create(['name' => 'Network management', 'network_address' => '10.10.40.0', 'cidr' => 24, 'gateway' => '10.10.40.1', 'description' => 'Fictional management subnet.']),
        ];
        $vlans = [
            10 => Vlan::query()->create(['vlan_number' => 10, 'name' => 'Servers', 'subnet_id' => $subnets['servers']->id, 'description' => 'Server systems.']),
            20 => Vlan::query()->create(['vlan_number' => 20, 'name' => 'Plant floor', 'subnet_id' => $subnets['plant']->id, 'description' => 'Production devices.']),
            30 => Vlan::query()->create(['vlan_number' => 30, 'name' => 'Office', 'subnet_id' => $subnets['office']->id, 'description' => 'Office users.']),
            40 => Vlan::query()->create(['vlan_number' => 40, 'name' => 'Management', 'subnet_id' => $subnets['mgmt']->id, 'description' => 'Device management.']),
        ];

        $deviceRows = [
            ['PLT-FW01', 'firewall', 'PG-NET-0001', 'SRV-RM', '10.10.40.1'],
            ['PLT-SW-CORE', 'switch', 'PG-NET-0002', 'SRV-RM', '10.10.40.2'],
            ['PLT-SW-PROD', 'switch', 'PG-NET-0003', 'PRC-HL', '10.10.40.3'],
            ['PLT-SW-PACK', 'switch', 'PG-NET-0004', 'PKG-HL', '10.10.40.4'],
            ['PLT-SW-OFFICE', 'switch', 'PG-NET-0005', 'ADM-BL', '10.10.40.5'],
            ['PLT-AP-PACK', 'wireless_access_point', 'PG-NET-0006', 'PKG-HL', '10.10.40.6'],
        ];
        $devices = [];
        foreach ($deviceRows as [$hostname, $type, $tag, $location, $ip]) {
            $devices[$hostname] = NetworkDevice::query()->create([
                'asset_id' => $assets[$tag]->id,
                'hostname' => $hostname,
                'device_type' => $type,
                'manufacturer' => $assets[$tag]->manufacturer,
                'model' => $assets[$tag]->model,
                'management_ip' => $ip,
                'location_id' => $locations[$location]->id,
                'status' => 'active',
                'notes' => 'Documented device. PlantGrid does not connect to it.',
            ]);
        }

        $addressRows = [
            ['10.10.40.1', 'mgmt', 40, 'PLT-FW01'],
            ['10.10.40.2', 'mgmt', 40, 'PLT-SW-CORE'],
            ['10.10.40.3', 'mgmt', 40, 'PLT-SW-PROD'],
            ['10.10.40.4', 'mgmt', 40, 'PLT-SW-PACK'],
            ['10.10.40.5', 'mgmt', 40, 'PLT-SW-OFFICE'],
            ['10.10.40.6', 'mgmt', 40, 'PLT-AP-PACK'],
            ['10.10.10.11', 'servers', 10, null, 'PG-SRV-0001'],
            ['10.10.10.12', 'servers', 10, null, 'PG-SRV-0002'],
            ['10.10.10.21', 'servers', 10, null, 'PG-SRV-0003'],
            ['10.10.10.31', 'servers', 10, null, 'PG-SRV-0004'],
            ['10.10.20.42', 'plant', 20, null, 'PG-LBL-0142'],
            ['10.10.20.18', 'plant', 20, null, 'PG-SCL-0301'],
        ];
        $addresses = [];
        foreach ($addressRows as $row) {
            $addresses[$row[0]] = IpAddress::query()->create([
                'ip_address' => $row[0],
                'subnet_id' => $subnets[$row[1]]->id,
                'vlan_id' => $vlans[$row[2]]->id,
                'assigned_network_device_id' => isset($row[3]) ? $devices[$row[3]]->id : null,
                'assigned_asset_id' => isset($row[4]) ? $assets[$row[4]]->id : null,
                'address_type' => 'static',
                'status' => 'active',
                'notes' => 'Static demonstration allocation.',
            ]);
        }

        $links = [
            ['PLT-FW01', 'PLT-SW-CORE', 'uplink', 'port1'],
            ['PLT-SW-CORE', 'PLT-SW-PROD', 'fiber', 'Gi1/0/1'],
            ['PLT-SW-CORE', 'PLT-SW-PACK', 'fiber', 'Gi1/0/2'],
            ['PLT-SW-CORE', 'PLT-SW-OFFICE', 'ethernet', 'Gi1/0/3'],
            ['PLT-SW-PACK', 'PLT-AP-PACK', 'ethernet', 'Gi1/0/12'],
        ];
        foreach ($links as [$source, $destination, $type, $port]) {
            NetworkLink::query()->create([
                'source_device_id' => $devices[$source]->id,
                'destination_device_id' => $devices[$destination]->id,
                'connection_type' => $type,
                'port_or_interface' => $port,
                'status' => 'active',
                'notes' => 'Documentation link only.',
            ]);
        }

        return compact('devices', 'addresses', 'subnets', 'vlans');
    }

    private function servers(array $assets, array $addresses): array
    {
        $rows = [
            ['PLT-AD01', 'PG-SRV-0001', 'Windows Server', '2022', 'Directory services', '10.10.10.11', 4, 16, 480],
            ['PLT-FS01', 'PG-SRV-0002', 'Windows Server', '2022', 'File services', '10.10.10.12', 4, 16, 4000],
            ['PLT-APP01', 'PG-SRV-0003', 'Ubuntu Server', '24.04 LTS', 'Application hosting', '10.10.10.21', 8, 32, 960],
            ['PLT-DB01', 'PG-SRV-0004', 'Ubuntu Server', '24.04 LTS', 'Database services', '10.10.10.31', 8, 64, 2000],
        ];
        $servers = [];
        foreach ($rows as $row) {
            $server = Server::query()->create([
                'asset_id' => $assets[$row[1]]->id,
                'hostname' => $row[0],
                'operating_system' => $row[2],
                'os_version' => $row[3],
                'server_role' => $row[4],
                'environment' => 'simulated_production',
                'cpu_cores' => $row[6],
                'ram_gb' => $row[7],
                'storage_gb' => $row[8],
                'ip_address_id' => $addresses[$row[5]]->id,
                'status' => 'active',
                'notes' => 'Example record only. This is not a live server.',
            ]);
            $servers[$row[0]] = $server;
        }

        ServerService::query()->create(['server_id' => $servers['PLT-AD01']->id, 'service_name' => 'Directory service', 'description' => 'Demonstration directory record.', 'port' => 389, 'protocol' => 'tcp', 'status' => 'running']);
        ServerService::query()->create(['server_id' => $servers['PLT-FS01']->id, 'service_name' => 'File share', 'description' => 'Department file shares.', 'port' => 445, 'protocol' => 'tcp', 'status' => 'running']);
        ServerService::query()->create(['server_id' => $servers['PLT-APP01']->id, 'service_name' => 'ERP application', 'description' => 'Fictional business application host.', 'port' => 443, 'protocol' => 'tcp', 'status' => 'running']);
        ServerService::query()->create(['server_id' => $servers['PLT-DB01']->id, 'service_name' => 'Database engine', 'description' => 'Demonstration database service.', 'port' => 3306, 'protocol' => 'tcp', 'status' => 'running']);

        return $servers;
    }

    private function applications($vendors, $departments)
    {
        return [
            'erp' => SystemApplication::query()->create([
                'name' => 'Northridge ERP', 'application_type' => 'ERP or business application',
                'vendor_id' => $vendors['Helio Office Supply']->id, 'owner_department_id' => $departments['FIN']->id,
                'version' => 'Demo 4.2', 'description' => 'Fictional business application record.', 'status' => 'active',
            ]),
            'label' => SystemApplication::query()->create([
                'name' => 'PackLabel', 'application_type' => 'Labelling system',
                'vendor_id' => $vendors['Kite Label Systems']->id, 'owner_department_id' => $departments['PRD']->id,
                'version' => '2.8', 'description' => 'Fictional label printing application.', 'status' => 'active',
            ]),
            'weigh' => SystemApplication::query()->create([
                'name' => 'FloorWeigh', 'application_type' => 'Weighing system',
                'vendor_id' => $vendors['Savannah Scale Works']->id, 'owner_department_id' => $departments['PRD']->id,
                'version' => '1.4', 'description' => 'Fictional intake weighing application.', 'status' => 'active',
            ]),
        ];
    }

    private function tickets(array $users, $departments, array $assets, array $applications): array
    {
        $categories = collect([
            'Hardware', 'Software', 'Network connectivity', 'Printer or scanner', 'User account and access',
            'Server and infrastructure', 'ERP and business application', 'Production technology', 'Security', 'Other ICT request',
        ])->mapWithKeys(fn ($name) => [$name => TicketCategory::query()->create(['name' => $name, 'description' => $name])]);

        $printer = $this->ticket('PG-2026-0148', $users['employee'], $departments['PRD'], $categories['Production technology'], $assets['PG-LBL-0142'], $applications['label'], 'Barcode label printer unavailable in Packaging Hall', 'The Zebra label printer at the packaging bench does not power on. Finished-goods cases cannot be labelled. This is a fictional production report for the demonstration.', 'high', 'new', null, now()->subHours(3), now()->addDay());
        $vpn = $this->ticket('PG-2026-0147', $users['productionManager'], $departments['WHS'], $categories['Network connectivity'], null, null, 'Warehouse supervisor cannot connect remotely', 'The warehouse supervisor reports that the remote access token is rejected. Work is continuing on site.', 'medium', 'in_progress', $users['technician'], now()->subDays(1), now()->addDays(2));
        $this->ticket('PG-2026-0146', $users['employee'], $departments['ICT'], $categories['Other ICT request'], null, null, 'Spare headset for the ICT office', 'Request for a spare USB headset. Not urgent.', 'low', 'pending', $users['technician'], now()->subDays(4), null);
        $this->ticket('PG-2026-0145', $users['manager'], $departments['ICT'], $categories['Server and infrastructure'], $assets['PG-SRV-0002'], null, 'File server disk space warning on PLT-FS01', 'The file server volume crossed the demonstration warning threshold. Old department archives were moved to a holding folder.', 'critical', 'resolved', $users['technician'], now()->subDays(6), null, 'Archives older than the retention note were moved. The volume returned below the warning line in the simulation.');
        $scale = $this->ticket('PG-2026-0144', $users['employee'], $departments['PRD'], $categories['Network connectivity'], $assets['PG-SCL-0301'], $applications['weigh'], 'Weigh scale computer lost its network connection', 'The intake scale PC showed a cable disconnected message. Processing paused for the demonstration incident.', 'high', 'closed', $users['technician'], now()->subDays(12), null, 'The floor outlet patch lead was reseated at PLT-SW-PROD. The scale PC obtained its address again.');
        $this->ticket('PG-2026-0143', $users['productionManager'], $departments['QA'], $categories['Printer or scanner'], $assets['PG-PRT-0401'], null, 'Paper jam on the QA laboratory printer', 'QA cannot print the morning checklist until the jam is cleared.', 'medium', 'assigned', $users['technician'], now()->subHours(8), now()->addHours(6));

        TicketComment::query()->create([
            'support_ticket_id' => $printer->id,
            'user_id' => $users['employee']->id,
            'comment' => 'The printer was working at the start of the shift. The display stays dark.',
            'visibility' => 'requester_visible',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);
        TicketComment::query()->create([
            'support_ticket_id' => $vpn->id,
            'user_id' => $users['technician']->id,
            'comment' => 'Internal note: token serial matches the demonstration record. Waiting for the user to confirm the device clock.',
            'visibility' => 'internal',
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        return ['printer' => $printer, 'scale' => $scale, 'categories' => $categories];
    }

    private function ticket($number, $requester, $department, $category, $asset, $application, $subject, $description, $priority, $status, $assignee, $reported, $due = null, $resolution = null): SupportTicket
    {
        $ticket = SupportTicket::query()->create([
            'ticket_number' => $number,
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'ticket_category_id' => $category->id,
            'asset_id' => $asset?->id,
            'system_application_id' => $application?->id,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => $status,
            'assigned_to' => $assignee?->id,
            'reported_at' => $reported,
            'due_at' => $due,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $reported->copy()->addHours(5) : null,
            'closed_at' => $status === 'closed' ? $reported->copy()->addHours(8) : null,
            'resolution_notes' => $resolution,
        ]);
        TicketStatusHistory::query()->create([
            'support_ticket_id' => $ticket->id,
            'old_status' => null,
            'new_status' => 'new',
            'changed_by' => $requester->id,
            'notes' => 'Ticket opened.',
            'created_at' => $reported,
        ]);
        if ($status !== 'new') {
            TicketStatusHistory::query()->create([
                'support_ticket_id' => $ticket->id,
                'old_status' => 'new',
                'new_status' => $status,
                'changed_by' => $assignee?->id ?? $requester->id,
                'notes' => 'Demonstration status.',
                'created_at' => $reported->copy()->addHour(),
            ]);
        }

        return $ticket;
    }

    private function production($departments, $vendors, array $users, array $assets, $locations, array $tickets): array
    {
        $label = ProductionSystem::query()->create([
            'name' => 'Packaging line labelling', 'system_type' => 'Labelling system',
            'department_id' => $departments['PRD']->id, 'vendor_id' => $vendors['Kite Label Systems']->id,
            'business_owner_id' => $users['productionManager']->id,
            'description' => 'Fictional labelling system for finished-goods cases. No machine control is connected.',
            'status' => 'under_maintenance',
        ]);
        $weigh = ProductionSystem::query()->create([
            'name' => 'Intake weighing', 'system_type' => 'Weighing system',
            'department_id' => $departments['PRD']->id, 'vendor_id' => $vendors['Savannah Scale Works']->id,
            'business_owner_id' => $users['productionManager']->id,
            'description' => 'Fictional intake weighing record.', 'status' => 'active',
        ]);
        ProductionSystem::query()->create([
            'name' => 'Finished goods inventory', 'system_type' => 'Inventory management system',
            'department_id' => $departments['WHS']->id, 'vendor_id' => $vendors['Helio Office Supply']->id,
            'business_owner_id' => $users['productionManager']->id,
            'description' => 'Fictional inventory application dependency.', 'status' => 'active',
        ]);

        $printer = ProductionEquipment::query()->create([
            'asset_id' => $assets['PG-LBL-0142']->id, 'production_system_id' => $label->id,
            'equipment_type' => 'Barcode label printer', 'manufacturer' => 'Zebra', 'model' => 'ZT411',
            'serial_number' => 'SN-LBL-0142', 'location_id' => $locations['PKG-HL']->id,
            'status' => 'under_maintenance', 'notes' => 'Linked to the open packaging ticket.',
        ]);
        $scale = ProductionEquipment::query()->create([
            'asset_id' => $assets['PG-SCL-0301']->id, 'production_system_id' => $weigh->id,
            'equipment_type' => 'Bench scale', 'manufacturer' => 'Avery', 'model' => 'ZM510',
            'serial_number' => 'SN-SCL-0301', 'location_id' => $locations['PRC-HL']->id, 'status' => 'active',
        ]);

        ProductionIncident::query()->create([
            'production_system_id' => $label->id,
            'production_equipment_id' => $printer->id,
            'support_ticket_id' => $tickets['printer']->id,
            'severity' => 'high',
            'description' => 'Label printer unavailable in the fictional packaging hall.',
            'operational_impact' => 'Case labelling on the demonstration line is paused.',
            'downtime_minutes' => 90,
            'status' => 'open',
            'reported_at' => now()->subHours(3),
        ]);
        ProductionIncident::query()->create([
            'production_system_id' => $weigh->id,
            'production_equipment_id' => $scale->id,
            'support_ticket_id' => $tickets['scale']->id,
            'severity' => 'high',
            'description' => 'Earlier intake scale network interruption.',
            'operational_impact' => 'Intake paused until the patch lead was reseated.',
            'downtime_minutes' => 35,
            'status' => 'resolved',
            'reported_at' => now()->subDays(12),
            'resolved_at' => now()->subDays(12)->addHours(5),
        ]);

        return ['equipment' => ['printer' => $printer, 'scale' => $scale]];
    }

    private function maintenance(array $users, $vendors, array $assets, array $servers, array $equipment): void
    {
        $upcoming = MaintenanceRecord::query()->create([
            'subject' => 'Quarterly inspection of packaging switch',
            'description' => 'Planned documentation review and visual inspection. No device commands.',
            'network_device_id' => null,
            'asset_id' => $assets['PG-NET-0004']->id,
            'assigned_technician_id' => $users['technician']->id,
            'vendor_id' => $vendors['Nimbus Network Co.']->id,
            'maintenance_type' => 'inspection',
            'priority' => 'medium',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(4),
        ]);
        AssetMaintenanceRecord::query()->create([
            'asset_id' => $assets['PG-NET-0004']->id,
            'technician_id' => $users['technician']->id,
            'vendor_id' => $vendors['Nimbus Network Co.']->id,
            'maintenance_type' => 'inspection',
            'description' => $upcoming->subject,
            'scheduled_at' => $upcoming->scheduled_at,
            'status' => 'scheduled',
        ]);

        MaintenanceRecord::query()->create([
            'subject' => 'Replace label printer power supply',
            'description' => 'Corrective work waiting on the open helpdesk ticket.',
            'asset_id' => $assets['PG-LBL-0142']->id,
            'production_equipment_id' => $equipment['printer']->id,
            'assigned_technician_id' => $users['technician']->id,
            'vendor_id' => $vendors['Kite Label Systems']->id,
            'maintenance_type' => 'corrective',
            'priority' => 'high',
            'status' => 'in_progress',
            'scheduled_at' => now()->subHours(2),
        ]);

        foreach (range(1, 5) as $month) {
            MaintenanceRecord::query()->create([
                'subject' => 'Server room inspection '.$month,
                'description' => 'Completed demonstration inspection.',
                'server_id' => $servers['PLT-APP01']->id,
                'assigned_technician_id' => $users['technician']->id,
                'maintenance_type' => 'preventive',
                'priority' => 'low',
                'status' => 'completed',
                'scheduled_at' => now()->subMonths($month)->startOfMonth()->addDays(3),
                'completed_at' => now()->subMonths($month)->startOfMonth()->addDays(3)->addHours(2),
            ]);
        }
    }

    private function security(array $users): void
    {
        SecurityIncident::query()->create([
            'reported_by' => $users['employee']->id,
            'assigned_to' => $users['manager']->id,
            'incident_type' => 'Reported phishing message',
            'severity' => 'medium',
            'description' => 'An employee forwarded a suspicious delivery notice. No credentials were entered. This is a fictional security record.',
            'status' => 'investigating',
            'reported_at' => now()->subDays(2),
        ]);

        $controls = [
            ['Endpoint protection review', 'Endpoint', 'Confirm demonstration endpoints show a current protection status.'],
            ['Patch review', 'Update management', 'Review outstanding operating system updates for fictional servers.'],
            ['Access review', 'Identity', 'Review active demonstration accounts and department access.'],
            ['Backup control', 'Recovery', 'Confirm simulated backup plans have a recent run and a recovery test.'],
        ];
        foreach ($controls as [$name, $category, $description]) {
            $control = SecurityControl::query()->create([
                'name' => $name,
                'control_category' => $category,
                'description' => $description,
                'owner_id' => $users['manager']->id,
                'status' => 'in_place',
                'review_frequency' => 'quarterly',
                'last_reviewed_at' => now()->subDays(20),
                'next_review_at' => now()->addMonths(2),
            ]);
            ComplianceCheck::query()->create([
                'security_control_id' => $control->id,
                'checked_by' => $users['auditor']->id,
                'review_date' => now()->subDays(20)->toDateString(),
                'result' => $name === 'Patch review' ? 'partial' : 'pass',
                'notes' => 'Fictional review evidence. No secrets are stored here.',
            ]);
        }
    }

    private function backups(array $users, array $servers, array $applications): void
    {
        $plans = [
            BackupPlan::query()->create(['name' => 'Directory server nightly', 'server_id' => $servers['PLT-AD01']->id, 'frequency' => 'daily', 'backup_type' => 'full', 'retention_days' => 30, 'status' => 'active', 'description' => 'Simulated plan only.']),
            BackupPlan::query()->create(['name' => 'File server nightly', 'server_id' => $servers['PLT-FS01']->id, 'frequency' => 'daily', 'backup_type' => 'incremental', 'retention_days' => 30, 'status' => 'active', 'description' => 'Simulated plan only.']),
            BackupPlan::query()->create(['name' => 'Application server weekly', 'server_id' => $servers['PLT-APP01']->id, 'system_application_id' => $applications['erp']->id, 'frequency' => 'weekly', 'backup_type' => 'full', 'retention_days' => 60, 'status' => 'active', 'description' => 'Simulated plan only.']),
            BackupPlan::query()->create(['name' => 'Database server nightly', 'server_id' => $servers['PLT-DB01']->id, 'frequency' => 'daily', 'backup_type' => 'full', 'retention_days' => 30, 'status' => 'active', 'description' => 'Simulated plan only.']),
        ];

        $successful = BackupRun::query()->create([
            'backup_plan_id' => $plans[3]->id,
            'started_at' => now()->subDay()->setTime(1, 0),
            'completed_at' => now()->subDay()->setTime(1, 40),
            'result' => 'successful',
            'size_mb' => 18420,
            'data_source' => 'simulated',
        ]);
        BackupRun::query()->create([
            'backup_plan_id' => $plans[1]->id,
            'started_at' => now()->subDay()->setTime(2, 0),
            'completed_at' => now()->subDay()->setTime(2, 15),
            'result' => 'failed',
            'failure_reason' => 'Simulated failure: demonstration volume reached the warning threshold before the copy finished.',
            'data_source' => 'simulated',
        ]);
        BackupRun::query()->create([
            'backup_plan_id' => $plans[0]->id,
            'started_at' => now()->subDay()->setTime(0, 30),
            'completed_at' => now()->subDay()->setTime(0, 55),
            'result' => 'successful',
            'size_mb' => 2400,
            'data_source' => 'simulated',
        ]);
        RecoveryTest::query()->create([
            'backup_run_id' => $successful->id,
            'tested_by' => $users['manager']->id,
            'test_date' => now()->subDays(10)->toDateString(),
            'result' => 'pass',
            'notes' => 'A fictional database was restored into an isolated demonstration folder and a row count was compared. No production system was touched.',
        ]);
    }

    private function monitoring(array $servers, array $devices): void
    {
        $definitions = [
            ['CPU utilisation', 'cpu', '%', 75, 90],
            ['Memory utilisation', 'memory', '%', 80, 95],
            ['Disk utilisation', 'disk', '%', 80, 95],
            ['Response time', 'response_time', 'ms', 200, 500],
            ['Packet loss', 'packet_loss', '%', 2, 5],
            ['Availability', 'availability', '%', null, null],
        ];
        foreach ($definitions as [$name, $key, $unit, $warning, $critical]) {
            MonitoringMetric::query()->create([
                'name' => $name,
                'metric_key' => $key,
                'unit' => $unit,
                'description' => 'Demonstration threshold. Not an industry standard.',
                'warning_threshold' => $warning,
                'critical_threshold' => $critical,
                'is_active' => true,
            ]);
        }

        $provider = app(MonitoringProvider::class);
        $collector = app(MetricCollectionService::class);
        $serverKeys = ['cpu', 'memory', 'disk', 'response_time', 'availability'];
        $deviceKeys = ['response_time', 'packet_loss', 'availability'];

        for ($hour = 0; $hour < 12; $hour++) {
            $at = now()->subHours(11 - $hour);
            $scenario = $hour >= 9 ? 'degraded' : 'normal';
            foreach ($servers as $server) {
                foreach ($serverKeys as $key) {
                    $value = $provider->reading($key, $server->hostname, $scenario);
                    if ($value !== null) {
                        $collector->record('server', $server->id, $server->hostname, $key, $value, $scenario, $at);
                    }
                }
            }
            foreach ($devices as $device) {
                foreach ($deviceKeys as $key) {
                    $value = $provider->reading($key, $device->hostname, $scenario);
                    if ($value !== null) {
                        $collector->record('network_device', $device->id, $device->hostname, $key, $value, $scenario, $at);
                    }
                }
            }
        }
    }

    private function settings(User $admin): void
    {
        SystemSetting::putValue('plant_name', 'Northridge Processing', $admin->id, 'Fictional demonstration plant');
        SystemSetting::putValue('monitoring_scenario', 'degraded', $admin->id, 'Active monitoring demonstration scenario');
        SystemSetting::putValue('monitoring_retention_days', '30', $admin->id, 'Simulated sample retention');
    }

    private function activity(array $users, SupportTicket $ticket): void
    {
        AuditLog::query()->create([
            'user_id' => $users['employee']->id,
            'action' => 'ticket.created',
            'entity_type' => 'support_ticket',
            'entity_id' => $ticket->id,
            'new_values' => ['ticket_number' => $ticket->ticket_number, 'priority' => 'high'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subHours(3),
        ]);
        AuditLog::query()->create([
            'user_id' => $users['manager']->id,
            'action' => 'auth.login',
            'entity_type' => 'user',
            'entity_id' => $users['manager']->id,
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subHour(),
        ]);
    }
}
