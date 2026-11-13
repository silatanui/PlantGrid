@extends('layouts.app')

@section('content')
    @include('partials.nav-back-crumb')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h1 class="pg-page-title">{{ $asset->asset_tag }}</h1>
            <p class="pg-page-lead">{{ $asset->name }}</p>
            @if (!empty($caseLinkedOnly))
                <p class="pg-page-lead mb-0">Opened from a case. Your role can view this linked device, but not the full Assets register.</p>
            @endif
        </div>
        <div class="d-flex gap-2">
            @if (auth()->user()->hasPermission('assets.update'))
                <a class="btn btn-pg-line" href="{{ route('assets.edit', $asset) }}">Edit</a>
            @endif
            @if (auth()->user()->hasPermission('assets.retire') && $asset->status !== 'retired')
                <form method="post" action="{{ route('assets.retire', $asset) }}">
                    @csrf
                    <button class="btn btn-pg-line" type="submit">Retire</button>
                </form>
            @endif
        </div>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            @include('partials.device-media', ['media' => \App\Support\DeviceMedia::forAsset($asset)])
            <section class="pg-card pg-card-pad mb-3">
                <h2 class="pg-section-title">Record</h2>
                <dl class="pg-kv">
                    <dt>Status</dt><dd>{!! \App\Support\Labels::badge($asset->status) !!}</dd>
                    <dt>Condition</dt><dd>{!! \App\Support\Labels::badge($asset->condition) !!}</dd>
                    <dt>Category</dt><dd>{{ $asset->category->name ?? '-' }}</dd>
                    <dt>Manufacturer</dt><dd>{{ $asset->manufacturer ?: '-' }} {{ $asset->model }}</dd>
                    <dt>Serial</dt><dd class="pg-mono">{{ $asset->serial_number ?: '-' }}</dd>
                    <dt>Department</dt><dd>{{ $asset->department->name ?? '-' }}</dd>
                    <dt>Location</dt>
                    <dd>
                        @if ($asset->location)
                            <a href="{{ route('locations.show', $asset->location) }}">{{ $asset->location->name }}</a>
                            <span class="text-muted small">· {{ $asset->location->code }}</span>
                        @else
                            -
                        @endif
                    </dd>
                    <dt>Vendor</dt><dd>{{ $asset->vendor->name ?? '-' }}</dd>
                    <dt>Warranty</dt><dd>{{ \App\Support\Format::date($asset->warranty_expiry, 'd M Y') }}</dd>
                    <dt>Notes</dt><dd class="pg-rich-note">{!! $asset->notes ? \App\Support\RichText::render($asset->notes) : e('-') !!}</dd>
                </dl>
            </section>
            <section class="pg-card mb-3">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Assignment history</h2></div>
                <table class="pg-table">
                    <thead><tr><th>Person</th><th>From</th><th>Returned</th></tr></thead>
                    <tbody>
                    @forelse ($asset->assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->user->name ?? 'Department pool' }}</td>
                            <td>{{ \App\Support\Format::date($assignment->assigned_at) }}</td>
                            <td>{{ \App\Support\Format::date($assignment->returned_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">No assignments yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>
        <div class="col-lg-5">
            @if (auth()->user()->hasPermission('assets.update'))
                <section class="pg-card pg-card-pad mb-3">
                    <h2 class="pg-section-title">Assign</h2>
                    <form method="post" action="{{ route('assets.assign', $asset) }}">
                        @csrf
                        <label class="form-label" for="user_id">Person</label>
                        <select class="form-select mb-2" id="user_id" name="user_id">
                            <option value="">Department pool</option>
                            @foreach ($users as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                        <label class="form-label" for="assign_department">Department</label>
                        <select class="form-select mb-2" id="assign_department" name="department_id">
                            <option value="">Keep current</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <label class="form-label mb-0" for="assign_notes">Notes</label>
                            <div class="pg-ai-field-actions" data-ai-field-actions>
                                <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="refine" data-ai-target="assign_notes">Refine with AI</button>
                                <button class="btn btn-sm btn-pg-line" type="button" data-ai-action="paraphrase" data-ai-target="assign_notes">Paraphrase</button>
                            </div>
                        </div>
                        <textarea class="form-control mb-2" id="assign_notes" name="notes" rows="2" data-ai-assist data-mentions placeholder="Assignment notes. Use @people / asset tags…"></textarea>
                        <button class="btn btn-pg" type="submit">Record assignment</button>
                    </form>
                    <form class="mt-2" method="post" action="{{ route('assets.return', $asset) }}">
                        @csrf
                        <button class="btn btn-pg-line" type="submit">Close current assignment</button>
                    </form>
                </section>
            @endif
            <section class="pg-card mb-3">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Linked tickets</h2></div>
                <table class="pg-table">
                    <tbody>
                    @forelse ($asset->tickets as $ticket)
                        <tr>
                            <td><a href="{{ route('helpdesk.show', $ticket) }}">{{ $ticket->ticket_number }}</a><div>{{ $ticket->subject }}</div></td>
                            <td>{!! \App\Support\Labels::badge($ticket->status) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No tickets linked.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
            <section class="pg-card">
                <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Status history</h2></div>
                <ul class="pg-timeline px-3">
                    @foreach ($asset->statusHistories->sortByDesc('changed_at') as $history)
                        <li>
                            {!! \App\Support\Labels::badge($history->new_status) !!}
                            <div class="text-muted">{{ \App\Support\Format::date($history->changed_at) }} · {{ $history->changer->name ?? 'System' }}</div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
@endsection
