<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetStatusHistory;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogger;
use App\Support\Csv;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        [$assetsQuery, $sort] = $this->filtered($request);
        $assets = $assetsQuery->paginate(12)->withQueryString();

        return view('assets.index', [
            'title' => 'ICT assets',
            'assets' => $assets,
            'categories' => AssetCategory::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'filters' => $request->only(['q', 'status', 'category', 'department', 'sort', 'direction']),
            'sort' => $sort,
        ]);
    }

    public function export(Request $request)
    {
        [$assetsQuery] = $this->filtered($request);
        $rows = $assetsQuery->get()->map(fn (Asset $asset) => [
            $asset->asset_tag,
            $asset->name,
            $asset->category->name ?? '',
            $asset->manufacturer,
            $asset->model,
            $asset->serial_number,
            $asset->department->name ?? '',
            $asset->location->name ?? '',
            $asset->status,
            $asset->condition,
            optional($asset->warranty_expiry)->toDateString(),
        ]);

        return Csv::download('plantgrid-assets.csv', [
            'Asset tag', 'Name', 'Category', 'Manufacturer', 'Model', 'Serial', 'Department', 'Location', 'Status', 'Condition', 'Warranty expiry',
        ], $rows);
    }

    public function create()
    {
        return view('assets.form', $this->formData(new Asset(), 'Register asset'));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $this->validateAsset($request);
        $data['asset_tag'] = $data['asset_tag'] ?: $this->nextTag();
        $data['created_by'] = $request->user()->id;
        $data['serial_number'] = $data['serial_number'] ?: null;

        $asset = DB::transaction(function () use ($data, $request) {
            $asset = Asset::query()->create($data);
            AssetStatusHistory::query()->create([
                'asset_id' => $asset->id,
                'previous_status' => null,
                'new_status' => $asset->status,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
                'notes' => 'Asset registered.',
            ]);

            return $asset;
        });

        $audit->log('asset.created', 'asset', $asset->id, [], ['asset_tag' => $asset->asset_tag]);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset '.$asset->asset_tag.' was registered.');
    }

    public function show(Asset $asset)
    {
        $this->guard($asset);
        $asset->load([
            'category', 'vendor', 'department', 'location',
            'assignments.user', 'assignments.department', 'assignments.assigner',
            'statusHistories.changer', 'maintenanceHistory.technician', 'tickets.requester',
        ]);

        return view('assets.show', [
            'title' => $asset->asset_tag,
            'asset' => $asset,
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Asset $asset)
    {
        $this->guard($asset);

        return view('assets.form', $this->formData($asset, 'Edit '.$asset->asset_tag));
    }

    public function update(Request $request, Asset $asset, AuditLogger $audit)
    {
        $this->guard($asset);
        abort_unless($request->user()->hasPermission('assets.update'), 403);
        $data = $this->validateAsset($request, $asset);
        $data['serial_number'] = $data['serial_number'] ?: null;
        $previous = $asset->status;
        $asset->update($data);

        if ($previous !== $asset->status) {
            AssetStatusHistory::query()->create([
                'asset_id' => $asset->id,
                'previous_status' => $previous,
                'new_status' => $asset->status,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
                'notes' => 'Status updated from the asset form.',
            ]);
        }

        $audit->log('asset.updated', 'asset', $asset->id, ['status' => $previous], ['status' => $asset->status, 'asset_tag' => $asset->asset_tag]);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset details were saved.');
    }

    public function retire(Request $request, Asset $asset, AuditLogger $audit)
    {
        $this->guard($asset);
        abort_unless($request->user()->hasPermission('assets.retire'), 403);
        $previous = $asset->status;
        $asset->update(['status' => 'retired']);
        AssetStatusHistory::query()->create([
            'asset_id' => $asset->id,
            'previous_status' => $previous,
            'new_status' => 'retired',
            'changed_by' => $request->user()->id,
            'changed_at' => now(),
            'notes' => $request->input('notes'),
        ]);
        $audit->log('asset.retired', 'asset', $asset->id, ['status' => $previous], ['status' => 'retired']);

        return back()->with('status', $asset->asset_tag.' was retired and kept in the register.');
    }

    public function assign(Request $request, Asset $asset, AuditLogger $audit)
    {
        $this->guard($asset);
        abort_unless($request->user()->hasPermission('assets.update'), 403);
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
            AssetAssignment::query()->create([
                'asset_id' => $asset->id,
                'user_id' => $data['user_id'] ?? null,
                'department_id' => $data['department_id'] ?? $asset->department_id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            if (! empty($data['department_id'])) {
                $asset->update(['department_id' => $data['department_id']]);
            }
        });

        $audit->log('asset.assigned', 'asset', $asset->id, [], ['user_id' => $data['user_id'] ?? null]);

        return back()->with('status', 'Assignment was recorded. Earlier assignments stay in the history.');
    }

    public function returnAssignment(Request $request, Asset $asset, AuditLogger $audit)
    {
        $this->guard($asset);
        abort_unless($request->user()->hasPermission('assets.update'), 403);
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        $audit->log('asset.returned', 'asset', $asset->id);

        return back()->with('status', 'The current assignment was closed.');
    }

    /**
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: TableSort}
     */
    private function filtered(Request $request): array
    {
        $sort = TableSort::from(
            $request,
            ['asset_tag', 'name', 'status', 'warranty_expiry'],
            'asset_tag',
        );

        $query = Asset::query()
            ->visibleTo($request->user())
            ->with(['category', 'department', 'location'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('asset_tag', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($query) => $query->where('asset_category_id', $request->integer('category')))
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->integer('department')));

        return [$sort->apply($query), $sort];
    }

    private function validateAsset(Request $request, ?Asset $asset = null): array
    {
        return $request->validate([
            'asset_tag' => ['nullable', 'string', 'max:40', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:160'],
            'asset_category_id' => ['required', 'exists:asset_categories,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120', Rule::unique('assets', 'serial_number')->ignore($asset?->id)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_expiry' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(['good', 'fair', 'poor'])],
            'status' => ['required', Rule::in(['active', 'spare', 'under_maintenance', 'damaged', 'lost', 'retired'])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function formData(Asset $asset, string $title): array
    {
        return [
            'title' => $title,
            'asset' => $asset,
            'categories' => AssetCategory::query()->orderBy('name')->get(),
            'vendors' => Vendor::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
        ];
    }

    private function guard(Asset $asset): void
    {
        abort_unless(
            Asset::query()->visibleTo(auth()->user())->whereKey($asset->id)->exists(),
            403
        );
    }

    private function nextTag(): string
    {
        $latest = Asset::query()->where('asset_tag', 'like', 'PG-AST-%')->orderByDesc('asset_tag')->value('asset_tag');
        $sequence = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return sprintf('PG-AST-%04d', $sequence);
    }
}
