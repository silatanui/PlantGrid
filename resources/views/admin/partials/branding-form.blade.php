<form class="pg-card pg-card-pad mb-3" method="post" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" id="branding">
    @csrf
    <h2 class="pg-section-title">Branding &amp; appearance</h2>
    <p class="text-muted small mb-3">Demo visitors can try the console with their own product name, plant label, logo and font. Accent colours stay in the account Appearance menu.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="brand_name">Product / brand name</label>
            <input class="form-control" id="brand_name" name="brand_name" value="{{ old('brand_name', $branding['brandName'] ?? 'PlantGrid') }}" required maxlength="80">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="brand_font">UI font</label>
            <select class="form-select" id="brand_font" name="brand_font" required>
                @foreach (($branding['fonts'] ?? []) as $fontKey => $fontLabel)
                    <option value="{{ $fontKey }}" @selected(old('brand_font', $branding['brandFont'] ?? 'ibm-plex') === $fontKey)>{{ $fontLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="branding_plant_name">Plant display name</label>
            <input class="form-control" id="branding_plant_name" name="plant_name" value="{{ old('plant_name', $branding['plantName'] ?? ($plantName ?? 'Northridge Processing')) }}" required maxlength="160">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="plant_chip_meta">Plant chip subtitle</label>
            <input class="form-control" id="plant_chip_meta" name="plant_chip_meta" value="{{ old('plant_chip_meta', $branding['plantChipMeta'] ?? 'Demo plant') }}" required maxlength="80">
        </div>
        <div class="col-12">
            <label class="form-label" for="brand_logo">Custom logo</label>
            <input class="form-control" id="brand_logo" name="brand_logo" type="file" accept=".png,.jpg,.jpeg,.svg,.webp,image/png,image/jpeg,image/svg+xml,image/webp">
            <p class="text-muted small mb-0 mt-1">{{ $branding['logoHint'] ?? 'Preferred: transparent PNG or SVG. Wide wordmark 1200×260 px (min 600×130).' }}</p>
            @if (! empty($branding['hasCustomLogo']))
                <div class="d-flex align-items-center gap-3 mt-2">
                    <img src="{{ $branding['brandLogoUrl'] }}" alt="Current logo" style="max-height: 40px; max-width: 220px; object-fit: contain;">
                    <label class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1">
                        <span class="form-check-label">Remove custom logo</span>
                    </label>
                </div>
            @endif
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-pg" type="submit">Save branding</button>
        <button class="btn btn-pg-line" type="submit" name="reset_branding" value="1">Reset to PlantGrid defaults</button>
    </div>
</form>
