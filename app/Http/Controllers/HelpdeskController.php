<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Department;
use App\Models\SupportTicket;
use App\Models\SystemApplication;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketService;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HelpdeskController extends Controller
{
    public function index(Request $request)
    {
        $employeeView = $request->user()->isEmployeeRequester();
        $defaultScope = $employeeView ? 'mine' : 'queue';
        $scope = $request->input('scope', $defaultScope);
        if ($employeeView && $scope === 'queue') {
            $scope = 'mine';
        }
        $viewMode = $request->input('view') === 'board' ? 'board' : 'list';
        $sort = TableSort::from(
            $request,
            ['ticket_number', 'subject', 'priority', 'status', 'reported_at'],
            'reported_at',
            'desc',
        );
        $query = SupportTicket::query()
            ->visibleTo($request->user())
            ->with(['requester', 'assignee', 'department', 'category', 'asset'])
            ->when($scope === 'mine', fn ($query) => $query->where('requester_id', $request->user()->id))
            ->when($scope === 'assigned', fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->integer('department')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('ticket_number', 'like', $term)->orWhere('subject', 'like', $term);
                });
            });
        $sort->apply($query);

        $tickets = $viewMode === 'board'
            ? $query->limit(120)->get()
            : $query->paginate(25)->withQueryString();

        $stages = ['new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed'];
        $columns = $viewMode === 'board'
            ? collect($stages)->mapWithKeys(fn (string $status) => [$status => $tickets->where('status', $status)->values()])
            : collect();

        return view('helpdesk.index', [
            'title' => $employeeView ? 'My cases' : 'Cases',
            'tickets' => $tickets,
            'departments' => Department::query()->orderBy('name')->get(),
            'scope' => $scope,
            'viewMode' => $viewMode,
            'columns' => $columns,
            'employeeView' => $employeeView,
            'filters' => $request->only(['q', 'status', 'priority', 'department', 'scope', 'view', 'sort', 'direction']),
            'sort' => $sort,
        ]);
    }

    public function create(Request $request)
    {
        return view('helpdesk.form', [
            'title' => 'New case',
            'categories' => TicketCategory::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'assets' => Asset::query()->visibleTo($request->user())->orderBy('asset_tag')->get(),
            'applications' => SystemApplication::query()->orderBy('name')->get(),
            'defaults' => [
                'department_id' => $request->user()->department_id,
                'priority' => 'medium',
            ],
        ]);
    }

    public function store(Request $request, TicketService $tickets)
    {
        abort_unless($request->user()->hasPermission('tickets.create'), 403);
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'ticket_category_id' => ['required', 'exists:ticket_categories,id'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'system_application_id' => ['nullable', 'exists:system_applications,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
        ]);

        if ($request->user()->limitsRecordsToDepartment()) {
            $data['department_id'] = $request->user()->department_id;
        }

        if (! empty($data['asset_id'])) {
            abort_unless(Asset::query()->visibleTo($request->user())->whereKey($data['asset_id'])->exists(), 403);
        }

        $ticket = $tickets->create($request->user(), $data);

        return redirect()->route('helpdesk.show', $ticket)->with('status', $ticket->ticket_number.' was opened.');
    }

    public function show(Request $request, SupportTicket $ticket, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        $ticket->load(['requester', 'assignee', 'department', 'category', 'asset.location', 'application', 'histories.changer', 'attachments.uploader', 'duplicateOf']);
        $canSeeInternal = $request->user()->hasAnyPermission(['tickets.update', 'audit.view']);
        $comments = $ticket->comments()->with('author')->latest()
            ->when(! $canSeeInternal, fn ($query) => $query->where('visibility', 'requester_visible'))
            ->get();

        return view('helpdesk.show', [
            'title' => $ticket->ticket_number,
            'ticket' => $ticket,
            'comments' => $comments,
            'canSeeInternal' => $canSeeInternal,
            'transitions' => $tickets->allowedTransitions($ticket, $request->user()),
            'canTakeOwnership' => $tickets->canTakeOwnership($ticket, $request->user()),
            'technicians' => User::query()
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['ICT Technician', 'ICT Manager', 'System Administrator']))
                ->orderBy('name')
                ->get(['id', 'name', 'last_seen_at', 'status']),
            'presence' => app(\App\Services\PresenceService::class),
        ]);
    }

    public function takeOwnership(Request $request, SupportTicket $ticket, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        $tickets->takeOwnership($ticket, $request->user());

        return back()->with('status', 'You now own '.$ticket->ticket_number.'.');
    }

    public function transition(Request $request, SupportTicket $ticket, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        $data = $request->validate([
            'status' => ['required', Rule::in(['assigned', 'in_progress', 'pending', 'resolved', 'closed'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'duplicate_of' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
            'record_maintenance' => ['nullable', 'boolean'],
            'restore_asset' => ['nullable', 'boolean'],
        ]);

        $tickets->transition($ticket, $request->user(), $data['status'], $data);

        $message = $data['status'] === 'closed' && $ticket->fresh()->close_reason === 'duplicate'
            ? 'Case closed as a duplicate.'
            : 'Ticket status is now '.str_replace('_', ' ', $data['status']).'.';

        return back()->with('status', $message);
    }

    public function comment(Request $request, SupportTicket $ticket, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:4000'],
            'visibility' => ['required', Rule::in(['requester_visible', 'internal'])],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,png,jpg,jpeg,txt,doc,docx'],
        ]);

        if ($data['visibility'] === 'internal' && ! $request->user()->hasPermission('tickets.update')) {
            $data['visibility'] = 'requester_visible';
        }

        $entry = $tickets->comment($ticket, $request->user(), $data['comment'], $data['visibility']);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('tickets/'.$ticket->id, 'local');
            TicketAttachment::query()->create([
                'support_ticket_id' => $ticket->id,
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($path),
                'storage_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Note posted.',
                'comment' => $this->commentPayload($entry, $request->user()),
            ]);
        }

        return back()->with('status', 'Note added.');
    }

    public function updateComment(Request $request, SupportTicket $ticket, TicketComment $comment, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        abort_unless($comment->support_ticket_id === $ticket->id, 404);
        abort_unless($request->user()->canEditTicketComment($comment), 403);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:4000'],
            'visibility' => ['required', Rule::in(['requester_visible', 'internal'])],
        ]);

        if ($data['visibility'] === 'internal' && ! $request->user()->hasPermission('tickets.update')) {
            $data['visibility'] = 'requester_visible';
        }

        $entry = $tickets->updateComment($comment, $request->user(), $data['comment'], $data['visibility']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Note updated.',
                'comment' => $this->commentPayload($entry, $request->user()),
            ]);
        }

        return back()->with('status', 'Note updated.');
    }

    public function deleteComment(Request $request, SupportTicket $ticket, TicketComment $comment, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        abort_unless($comment->support_ticket_id === $ticket->id, 404);
        abort_unless($request->user()->canDeleteTicketComment($comment), 403);

        $tickets->deleteComment($comment, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Note deleted.',
                'comment_id' => $comment->id,
            ]);
        }

        return back()->with('status', 'Note deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function commentPayload(TicketComment $comment, $user): array
    {
        return [
            'id' => $comment->id,
            'author' => $comment->author?->name ?? 'User',
            'author_id' => $comment->user_id,
            'comment' => $comment->comment,
            'html' => \App\Support\RichText::render($comment->comment),
            'visibility' => $comment->visibility,
            'is_internal' => $comment->visibility === 'internal',
            'created_at' => \App\Support\Format::date($comment->created_at),
            'updated_at' => \App\Support\Format::date($comment->updated_at),
            'edited' => $comment->updated_at && $comment->created_at && $comment->updated_at->gt($comment->created_at->copy()->addSecond()),
            'can_edit' => $user->canEditTicketComment($comment),
            'can_delete' => $user->canDeleteTicketComment($comment),
            'update_url' => route('helpdesk.comment.update', [$comment->support_ticket_id, $comment]),
            'delete_url' => route('helpdesk.comment.destroy', [$comment->support_ticket_id, $comment]),
        ];
    }

    public function download(Request $request, SupportTicket $ticket, TicketAttachment $attachment)
    {
        $this->visible($request, $ticket);
        abort_unless($attachment->support_ticket_id === $ticket->id, 404);

        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_name);
    }

    private function visible(Request $request, SupportTicket $ticket): void
    {
        abort_unless(
            SupportTicket::query()->visibleTo($request->user())->whereKey($ticket->id)->exists(),
            403
        );
    }
}
