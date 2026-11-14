@extends('layouts.app')

@section('content')
    @php
        $isEdit = $asset->exists;
        $specs = old('specifications', is_array($asset->specifications) ? $asset->specifications : []);
        $specOs = old('spec_os', $specs['os'] ?? '');
        $specCapacity = old('spec_capacity', $specs['capacity'] ?? '');
        $specPower = old('spec_power', $specs['power'] ?? '');
        $specPorts = old('spec_ports', $specs['ports'] ?? '');
        $specFirmware = old('spec_firmware', $specs['firmware'] ?? '');
        $specOther = old('spec_other', $specs['other'] ?? '');
    @endphp
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Assets</div>
            <h1 class="pg-page-title">{{ $title }}</h1>
            <p class="pg-page-lead">
                @if ($isEdit)
                    Update identity, ownership, lifecycle and evidence for this register item.
                @else
                    Capture identity, placement, lifecycle and evidence so the register stays useful for cases, monitoring and audits.
                @endif
            </p>
        </div>
        <div class="pg-page-intro-actions">
            <a class="btn btn-pg-line" href="{{ $isEdit ? route('assets.show', $asset) : route('assets.index') }}">Cancel</a>
        </div>
    </section>

    @if ($errors->any())
        <div class="pg-alert is-error mb-3" role="alert">
            <strong>Check the highlighted fields.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        class="pg-asset-form"
        method="post"
        action="{{ $isEdit ? route('assets.update', $asset) : route('assets.store') }}"
        enctype="multipart/form-data"
        data-asset-form
    >
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="pg-asset-form-grid">
            <div class="pg-asset-form-main">
                <section class="pg-card pg-card-pad pg-form-section">
                    <header class="pg-form-section-head">
                        <h2 class="pg-section-title mb-0">Identity</h2>
                        <p class="pg-form-section-lead">What this asset is and how it appears in the register.</p>
                    </header>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="asset_tag">Asset tag</label>
                            <input class="form-control pg-mono" id="asset_tag" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" placeholder="Blank = auto PG-AST-####" maxlength="40">
                            <p class="form-text mb-0">Unique plant tag. Leave blank to generate.</p>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="name">Display name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name', $asset->name) }}" required maxlength="160" placeholder="e.g. Packaging label printer">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="asset_category_id">Category</label>
                            <select class="form-select" id="asset_category_id" name="asset_category_id" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('asset_category_id', $asset->asset_category_id) == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                @foreach (['active', 'spare', 'under_maintenance', 'damaged', 'lost', 'retired'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $asset->status ?: 'active') === $status)>{{ \App\Support\Labels::status($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="condition">Condition</label>
                            <select class="form-select" id="condition" name="condition" required>
                                @foreach (['good', 'fair', 'poor'] as $condition)
                                    <option value="{{ $condition }}" @selected(old('condition', $asset->condition ?: 'good') === $condition)>{{ ucfirst($condition) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="criticality">Business criticality</label>
                            <select class="form-select" id="criticality" name="criticality" required>
                                @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('criticality', $asset->criticality ?: 'medium') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="form-text mb-0">How hard production or ICT is hit if this fails.</p>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="parent_asset_id">Parent asset <span class="text-muted fw-normal">(optional)</span></label>
                            <select class="form-select" id="parent_asset_id" name="parent_asset_id">
                                <option value="">Standalone asset</option>
                                @foreach ($parentAssets as $parent)
                                    <option value="{{ $parent->id }}" @selected(old('parent_asset_id', $asset->parent_asset_id) == $parent->id)>
                                        {{ $parent->asset_tag }} · {{ $parent->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="form-text mb-0">Use for modules, PSUs, monitors or cards that belong to a host.</p>
                        </div>
                    </div>
                </section>

                <section class="pg-card pg-card-pad pg-form-section">
                    <header class="pg-form-section-head">
                        <h2 class="pg-section-title mb-0">Hardware</h2>
                        <p class="pg-form-section-lead">Make, model and identifiers for spares, warranty and vendor calls.</p>
                    </header>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="vendor_id">Preferred vendor</label>
                            <select class="form-select" id="vendor_id" name="vendor_id">
                                <option value="">None</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" @selected(old('vendor_id', $asset->vendor_id) == $vendor->id)>{{ $vendor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="manufacturer">Manufacturer</label>
                            <input class="form-control" id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $asset->manufacturer) }}" maxlength="120" placeholder="e.g. Zebra">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="model">Model</label>
                            <input class="form-control" id="model" name="model" value="{{ old('model', $asset->model) }}" maxlength="120" placeholder="e.g. ZT411">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="serial_number">Serial number</label>
                            <input class="form-control pg-mono" id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alternate_id">Barcode / alternate ID</label>
                            <input class="form-control pg-mono" id="alternate_id" name="alternate_id" value="{{ old('alternate_id', $asset->alternate_id) }}" maxlength="120" placeholder="Service tag, barcode, RFID">
                        </div>
                    </div>
                </section>

                <section class="pg-card pg-card-pad pg-form-section">
                    <header class="pg-form-section-head">
                        <h2 class="pg-section-title mb-0">Placement and ownership</h2>
                        <p class="pg-form-section-lead">Where it lives and who is responsible day to day.</p>
                    </header>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="department_id">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Unassigned</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $asset->department_id) == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="location_id">Location</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">Unassigned</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected(old('location_id', $asset->location_id) == $location->id)>{{ $location->name }} · {{ $location->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        @unless ($isEdit)
                            <div class="col-md-6">
                                <label class="form-label" for="assign_user_id">Assign to person <span class="text-muted fw-normal">(optional)</span></label>
                                <select class="form-select" id="assign_user_id" name="assign_user_id">
                                    <option value="">Leave unassigned / pool</option>
                                    @foreach ($users as $person)
                                        <option value="{{ $person->id }}" @selected(old('assign_user_id') == $person->id)>{{ $person->name }} · {{ $person->roleLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="assign_notes">Assignment note</label>
                                <input class="form-control" id="assign_notes" name="assign_notes" value="{{ old('assign_notes') }}" maxlength="500" placeholder="e.g. Issued to Packaging lead for line 2">
                            </div>
                        @endunless
                    </div>
                </section>

                <section class="pg-card pg-card-pad pg-form-section">
                    <header class="pg-form-section-head">
                        <h2 class="pg-section-title mb-0">Lifecycle and value</h2>
                        <p class="pg-form-section-lead">Purchase, warranty and expected service life.</p>
                    </header>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="purchase_date">Purchase date</label>
                            <input class="form-control" id="purchase_date" name="purchase_date" type="date" value="{{ old('purchase_date', optional($asset->purchase_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="purchase_cost">Purchase cost</label>
                            <div class="input-group">
                                <select class="form-select" id="purchase_currency" name="purchase_currency" style="max-width: 7rem">
                                    @foreach (['KES', 'USD', 'EUR', 'GBP'] as $currency)
                                        <option value="{{ $currency }}" @selected(old('purchase_currency', $asset->purchase_currency ?: 'KES') === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                                <input class="form-control" id="purchase_cost" name="purchase_cost" type="number" min="0" step="0.01" value="{{ old('purchase_cost', $asset->purchase_cost) }}" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="warranty_expiry">Warranty expiry</label>
                            <input class="form-control" id="warranty_expiry" name="warranty_expiry" type="date" value="{{ old('warranty_expiry', optional($asset->warranty_expiry)->toDateString()) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="expected_life_months">Expected life (months)</label>
                            <input class="form-control" id="expected_life_months" name="expected_life_months" type="number" min="1" max="600" value="{{ old('expected_life_months', $asset->expected_life_months) }}" placeholder="e.g. 60">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="purchase_order">Purchase order</label>
                            <input class="form-control pg-mono" id="purchase_order" name="purchase_order" value="{{ old('purchase_order', $asset->purchase_order) }}" maxlength="80">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="invoice_number">Invoice number</label>
                            <input class="form-control pg-mono" id="invoice_number" name="invoice_number" value="{{ old('invoice_number', $asset->invoice_number) }}" maxlength="80">
                        </div>
                    </div>
                </section>

                <section class="pg-card pg-card-pad pg-form-section">
                    <header class="pg-form-section-head">
                        <h2 class="pg-section-title mb-0">Network and technical specs</h2>
                        <p class="pg-form-section-lead">Fill what applies. Leave blank for non-network gear.</p>
                    </header>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="hostname">Hostname</label>
                            <input class="form-control pg-mono" id="hostname" name="hostname" value="{{ old('hostname', $asset->hostname) }}" maxlength="120" placeholder="PLT-…">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ip_address">Management IP</label>
                            <input class="form-control pg-mono" id="ip_address" name="ip_address" value="{{ old('ip_address', $asset->ip_address) }}" maxlength="64" placeholder="10.x.x.x">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mac_address">MAC address</label>
                            <input class="form-control pg-mono" id="mac_address" name="mac_address" value="{{ old('mac_address', $asset->mac_address) }}" maxlength="32" placeholder="AA:BB:…">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="spec_os">OS / firmware family</label>
                            <input class="form-control" id="spec_os" name="spec_os" value="{{ $specOs }}" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="spec_firmware">Firmware / version</label>
                            <input class="form-control" id="spec_firmware" name="spec_firmware" value="{{ $specFirmware }}" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="spec_capacity">Capacity / size</label>
                            <input class="form-control" id="spec_capacity" name="spec_capacity" value="{{ $specCapacity }}" maxlength="120" placeholder="RAM, disk, print width…">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="spec_power">Power</label>
                            <input class="form-control" id="spec_power" name="spec_power" value="{{ $specPower }}" maxlength="120" placeholder="230V / PoE / battery">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="spec_ports">Ports / interfaces</label>
                            <input class="form-control" id="spec_ports" name="spec_ports" value="{{ $specPorts }}" maxlength="160" placeholder="USB, Ethernet, serial…">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="spec_other">Other specs</label>
                            <input class="form-control" id="spec_other" name="spec_other" value="{{ $specOther }}" maxlength="200">
                        </div>
                    </div>
                </section>

                <section class="pg-card pg-card-pad pg-form-section">
                    <header class="pg-form-section-head">
                        <h2 class="pg-section-title mb-0">Notes</h2>
                        <p class="pg-form-section-lead">Context for ICT: quirks, access, spare location, handover detail.</p>
                    </header>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <label class="form-label mb-0" for="notes">Free-text notes</label>
                        <div class="pg-ai-field-actions" data-ai-field-actions>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="notes">Refine with AI</button>
                            <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="notes">Paraphrase</button>
                        </div>
                    </div>
                    <textarea class="form-control mt-2" id="notes" name="notes" rows="4" data-ai-assist data-mentions placeholder="Asset notes. Use @people and tags like PG-LBL-0142…">{{ old('notes', $asset->notes) }}</textarea>
                </section>
            </div>

            <aside class="pg-asset-form-side">
                <section class="pg-card pg-card-pad pg-form-section pg-form-section-sticky">
                    <header class="pg-form-section-head">
                        <h2 class="pg-section-title mb-0">Evidence</h2>
                        <p class="pg-form-section-lead">Photos and files for identification, invoices and audits.</p>
                    </header>

                    <div class="pg-attach-composer mb-3" data-attach-composer>
                        <label class="form-label" for="photo">Primary photo</label>
                        <label class="pg-attach-drop" for="photo">
                            <input class="pg-attach-input" id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-attach-input>
                            <span class="pg-attach-drop-copy">
                                <i data-lucide="image" aria-hidden="true"></i>
                                <strong>Drop a photo or click to browse</strong>
                                <span>PNG, JPG or WebP · up to 8 MB</span>
                            </span>
                        </label>
                        <div class="pg-attach-pending" data-attach-pending hidden>
                            <div class="pg-attach-pending-media" data-attach-pending-media></div>
                            <div class="pg-attach-pending-meta">
                                <strong data-attach-pending-name></strong>
                                <span data-attach-pending-size></span>
                            </div>
                            <button class="btn btn-sm btn-pg-line" type="button" data-attach-clear>Remove</button>
                        </div>
                        @if ($asset->photo_path)
                            <p class="form-text mb-0">Current photo is kept unless you upload a replacement.</p>
                            <div class="pg-asset-photo-current mt-2">
                                <img src="{{ asset('storage/'.$asset->photo_path) }}" alt="Current asset photo">
                            </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="documents">Documents</label>
                        <label class="pg-attach-drop" for="documents">
                            <input class="pg-attach-input" id="documents" name="documents[]" type="file" accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.doc,.docx,.xls,.xlsx,image/*" multiple data-asset-docs-input>
                            <span class="pg-attach-drop-copy">
                                <i data-lucide="files" aria-hidden="true"></i>
                                <strong>Add invoices, manuals or evidence</strong>
                                <span>PDF, Office or images · up to 5 files · 10 MB each</span>
                            </span>
                        </label>
                        <ul class="pg-asset-docs-list" data-asset-docs-list hidden></ul>
                    </div>

                    @if ($isEdit && $asset->attachments->isNotEmpty())
                        <div class="pg-asset-existing-files">
                            <h3 class="pg-form-subhead">On file</h3>
                            <ul class="pg-asset-file-list">
                                @foreach ($asset->attachments as $attachment)
                                    <li>
                                        <a href="{{ route('assets.attachment', [$asset, $attachment]) }}">{{ $attachment->original_name }}</a>
                                        <span class="text-muted">{{ $attachment->kindLabel() }} · {{ $attachment->humanSize() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="pg-asset-form-actions">
                        <button class="btn btn-pg w-100" type="submit">{{ $isEdit ? 'Save changes' : 'Register asset' }}</button>
                        <a class="btn btn-pg-line w-100" href="{{ $isEdit ? route('assets.show', $asset) : route('assets.index') }}">Cancel</a>
                    </div>
                </section>

                <section class="pg-card pg-card-pad pg-form-hint">
                    <h3 class="pg-form-subhead">Coverage checklist</h3>
                    <ul class="pg-form-checklist">
                        <li>New floor device: identity, location, photo, serial</li>
                        <li>Spare / store stock: status Spare, condition, location</li>
                        <li>Network gear: hostname, IP, MAC, firmware</li>
                        <li>Assigned laptop / scanner: person + department</li>
                        <li>Component of a host: set parent asset</li>
                        <li>Purchased recently: cost, PO, invoice file</li>
                    </ul>
                </section>
            </aside>
        </div>
    </form>
@endsection
