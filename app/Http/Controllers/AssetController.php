<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetAttachment;
use App\Models\AssetCategory;
use App\Models\AssetStatusHistory;
use App\Models\Department;
use App\Models\Location;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogger;
use App\Support\Csv;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $asset->criticality,
            optional($asset->warranty_expiry)->toDateString(),
        ]);

        return Csv::download('plantgrid-assets.csv', [
            'Asset tag', 'Name', 'Category', 'Manufacturer', 'Model', 'Serial', 'Department', 'Location', 'Status', 'Condition', 'Criticality', 'Warranty expiry',
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
        $data['alternate_id'] = $data['alternate_id'] ?: null;
        $data['specifications'] = $this->specificationsFromRequest($request);

        $assignUserId = $data['assign_user_id'] ?? null;
        $assignNotes = $data['assign_notes'] ?? null;
        unset($data['assign_user_id'], $data['assign_notes'], $data['photo'], $data['documents'], $data['spec_os'], $data['spec_capacity'], $data['spec_power'], $data['spec_ports'], $data['spec_firmware'], $data['spec_other']);

        $asset = DB::transaction(function () use ($data, $request, $assignUserId, $assignNotes) {
            if ($request->hasFile('photo')) {
                $data['photo_path'] = $request->file('photo')->store('assets/photos', 'public');
            }

            $asset = Asset::query()->create($data);
            AssetStatusHistory::query()->create([
                'asset_id' => $asset->id,
                'previous_status' => null,
                'new_status' => $asset->status,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
                'notes' => 'Asset registered.',
            ]);

            if ($assignUserId || ($data['department_id'] ?? null)) {
                AssetAssignment::query()->create([
                    'asset_id' => $asset->id,
                    'user_id' => $assignUserId,
                    'department_id' => $data['department_id'] ?? null,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'notes' => $assignNotes,
                ]);
            }

            $this->storeUploadedDocuments($request, $asset);

            return $asset;
        });

        $audit->log('asset.created', 'asset', $asset->id, [], ['asset_tag' => $asset->asset_tag]);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset '.$asset->asset_tag.' was registered.');
    }

    public function show(Request $request, Asset $asset)
    {
        $this->guardShow($request, $asset);
        $asset->load([
            'category', 'vendor', 'department', 'location', 'parent', 'children',
            'assignments.user', 'assignments.department', 'assignments.assigner',
            'statusHistories.changer', 'maintenanceHistory.technician', 'tickets.requester',
            'attachments.uploader',
        ]);

        return view('assets.show', [
            'title' => $asset->asset_tag,
            'asset' => $asset,
            'users' => $request->user()->hasPermission('assets.update')
                ? User::query()->where('status', 'active')->orderBy('name')->get()
                : collect(),
            'departments' => $request->user()->hasPermission('assets.update')
                ? Department::query()->orderBy('name')->get()
                : collect(),
            'caseLinkedOnly' => ! $request->user()->hasPermission('assets.view'),
        ]);
    }

    public function edit(Asset $asset)
    {
        $this->guard($asset);
        $asset->load('attachments');

        return view('assets.form', $this->formData($asset, 'Edit '.$asset->asset_tag));
    }

    public function update(Request $request, Asset $asset, AuditLogger $audit)
    {
        $this->guard($asset);
        abort_unless($request->user()->hasPermission('assets.update'), 403);
        $data = $this->validateAsset($request, $asset);
        $data['serial_number'] = $data['serial_number'] ?: null;
        $data['alternate_id'] = $data['alternate_id'] ?: null;
        $data['specifications'] = $this->mergeSpecifications($asset, $request);
        unset($data['assign_user_id'], $data['assign_notes'], $data['photo'], $data['documents'], $data['spec_os'], $data['spec_capacity'], $data['spec_power'], $data['spec_ports'], $data['spec_firmware'], $data['spec_other']);

        $previous = $asset->status;

        DB::transaction(function () use ($request, $asset, $data) {
            if ($request->hasFile('photo')) {
                if ($asset->photo_path && Storage::disk('public')->exists($asset->photo_path)) {
                    Storage::disk('public')->delete($asset->photo_path);
                }
                $data['photo_path'] = $request->file('photo')->store('assets/photos', 'public');
            }

            $asset->update($data);
            $this->storeUploadedDocuments($request, $asset);
        });

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

    public function downloadAttachment(Request $request, Asset $asset, AssetAttachment $attachment): StreamedResponse
    {
        $this->guardShow($request, $asset);
        abort_unless($attachment->asset_id === $asset->id, 404);

        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_name);
    }

    public function destroyAttachment(Request $request, Asset $asset, AssetAttachment $attachment, AuditLogger $audit)
    {
        $this->guard($asset);
        abort_unless($request->user()->hasPermission('assets.update'), 403);
        abort_unless($attachment->asset_id === $asset->id, 404);

        if (Storage::disk('local')->exists($attachment->storage_path)) {
            Storage::disk('local')->delete($attachment->storage_path);
        }
        $attachment->delete();
        $audit->log('asset.attachment.delete', 'asset', $asset->id, [], ['name' => $attachment->original_name]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'File removed.']);
        }

        return back()->with('status', 'Attachment removed.');
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
                        ->orWhere('alternate_id', 'like', $term)
                        ->orWhere('hostname', 'like', $term)
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
            'alternate_id' => ['nullable', 'string', 'max:120'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'parent_asset_id' => [
                'nullable',
                'exists:assets,id',
                Rule::notIn(array_filter([(string) $asset?->id])),
            ],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'purchase_currency' => ['nullable', Rule::in(['KES', 'USD', 'EUR', 'GBP'])],
            'purchase_order' => ['nullable', 'string', 'max:80'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'warranty_expiry' => ['nullable', 'date'],
            'expected_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'hostname' => ['nullable', 'string', 'max:120'],
            'ip_address' => ['nullable', 'string', 'max:64'],
            'mac_address' => ['nullable', 'string', 'max:32'],
            'condition' => ['required', Rule::in(['good', 'fair', 'poor'])],
            'status' => ['required', Rule::in(['active', 'spare', 'under_maintenance', 'damaged', 'lost', 'retired'])],
            'criticality' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'assign_user_id' => ['nullable', 'exists:users,id'],
            'assign_notes' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:8192'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp,gif,txt,doc,docx,xls,xlsx'],
            'spec_os' => ['nullable', 'string', 'max:120'],
            'spec_capacity' => ['nullable', 'string', 'max:120'],
            'spec_power' => ['nullable', 'string', 'max:120'],
            'spec_ports' => ['nullable', 'string', 'max:160'],
            'spec_firmware' => ['nullable', 'string', 'max:120'],
            'spec_other' => ['nullable', 'string', 'max:200'],
        ]);
    }

    private function specificationsFromRequest(Request $request): array
    {
        return array_filter([
            'os' => $request->input('spec_os'),
            'capacity' => $request->input('spec_capacity'),
            'power' => $request->input('spec_power'),
            'ports' => $request->input('spec_ports'),
            'firmware' => $request->input('spec_firmware'),
            'other' => $request->input('spec_other'),
        ], fn ($value) => filled($value));
    }

    private function mergeSpecifications(Asset $asset, Request $request): array
    {
        $existing = is_array($asset->specifications) ? $asset->specifications : [];
        $kept = collect($existing)->only(['image_key', 'summary'])->all();

        return array_merge($kept, $this->specificationsFromRequest($request));
    }

    private function storeUploadedDocuments(Request $request, Asset $asset): void
    {
        $files = $request->file('documents', []);
        if (! is_array($files)) {
            $files = array_filter([$files]);
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store('assets/'.$asset->id, 'local');
            $mime = (string) $file->getClientMimeType();
            $kind = str_starts_with($mime, 'image/') ? 'photo' : 'document';
            if (preg_match('/invoice|po|receipt/i', $file->getClientOriginalName())) {
                $kind = 'invoice';
            }

            AssetAttachment::query()->create([
                'asset_id' => $asset->id,
                'uploaded_by' => $request->user()->id,
                'kind' => $kind,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($path),
                'storage_path' => $path,
                'mime_type' => $mime,
                'file_size' => (int) $file->getSize(),
            ]);
        }
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
            'users' => User::query()->with('roles:id,name')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'department_id']),
            'parentAssets' => Asset::query()
                ->when($asset->exists, fn ($query) => $query->whereKeyNot($asset->id))
                ->orderBy('asset_tag')
                ->limit(200)
                ->get(['id', 'asset_tag', 'name']),
        ];
    }

    private function guard(Asset $asset): void
    {
        abort_unless(
            Asset::query()->visibleTo(auth()->user())->whereKey($asset->id)->exists(),
            403
        );
    }

    private function guardShow(Request $request, Asset $asset): void
    {
        $user = $request->user();

        if ($user->hasPermission('assets.view')
            && Asset::query()->visibleTo($user)->whereKey($asset->id)->exists()) {
            return;
        }

        abort_unless(
            SupportTicket::query()
                ->visibleTo($user)
                ->where('asset_id', $asset->id)
                ->exists(),
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
