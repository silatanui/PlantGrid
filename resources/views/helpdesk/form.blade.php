@extends('layouts.app')

@section('content')
    <div class="pg-object">Cases</div>
    <h1 class="pg-page-title mb-1">New case</h1>
    <p class="pg-page-lead mb-3">
        @if (auth()->user()->isEmployeeRequester())
            Describe the problem. ICT will assign and resolve the case; you can follow progress under My cases.
        @else
            Open a support request for the demonstration plant.
        @endif
    </p>
    <form class="pg-card pg-card-pad" method="post" action="{{ route('helpdesk.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="subject">Subject</label>
                <input class="form-control" id="subject" name="subject" value="{{ old('subject') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="priority">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    @foreach (['critical', 'high', 'medium', 'low'] as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', $defaults['priority']) === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="department_id">Department</label>
                <select class="form-select" id="department_id" name="department_id" @disabled(auth()->user()->limitsRecordsToDepartment())>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id', $defaults['department_id']) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                @if (auth()->user()->limitsRecordsToDepartment())
                    <input type="hidden" name="department_id" value="{{ auth()->user()->department_id }}">
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ticket_category_id">Category</label>
                <select class="form-select" id="ticket_category_id" name="ticket_category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('ticket_category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="asset_id">Asset</label>
                <select class="form-select" id="asset_id" name="asset_id">
                    <option value="">None</option>
                    @foreach ($assets as $asset)
                        <option value="{{ $asset->id }}" @selected(old('asset_id') == $asset->id)>{{ $asset->asset_tag }} · {{ $asset->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <label class="form-label mb-0" for="description">Description</label>
                    <div class="pg-ai-field-actions" data-ai-field-actions>
                        <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="description">Refine with AI</button>
                        <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="description">Paraphrase</button>
                    </div>
                </div>
                <textarea class="form-control mt-2" id="description" name="description" rows="5" required data-ai-assist data-mentions placeholder="Describe the issue. Tag people with @ and assets/cases with their IDs…">{{ old('description') }}</textarea>
                <p class="pg-mention-hint mt-1 mb-0">Examples: <span class="pg-mono">@faith.cheruiyot</span>, <span class="pg-mono">PG-LBL-0142</span>, <span class="pg-mono">PG-2026-0148</span></p>
            </div>
        </div>
        <button class="btn btn-pg mt-3" type="submit">Open ticket</button>
    </form>
@endsection
