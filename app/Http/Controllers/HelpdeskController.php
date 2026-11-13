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
use App\Services\AuditLogger;
use App\Services\TicketService;
use App\Support\SearchPages;
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
        $sort = TableSort::from(
            $request,
            ['ticket_number', 'subject', 'priority', 'status', 'reported_at'],
            'reported_at',
            'desc',
        );
        $statusFilter = trim((string) $request->input('status', ''));

        $query = SupportTicket::query()
            ->visibleTo($request->user())
            ->with(['requester', 'assignee', 'department', 'category', 'asset'])
            ->when($scope === 'mine', fn ($query) => $query->involving($request->user()))
            ->when($scope === 'assigned', fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($statusFilter !== '', fn ($query) => SearchPages::applyStatusFilter($query, $statusFilter))
            ->when($request->boolean('unassigned'), fn ($query) => $query->whereNull('assigned_to'))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->integer('department')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('ticket_number', 'like', $term)->orWhere('subject', 'like', $term);
                });
            });
        $sort->apply($query);

        $tickets = $query->paginate(25)->withQueryString();

        $pageTitle = match (true) {
            $scope === 'mine' && $statusFilter === 'open' => 'My open cases',
            $scope === 'mine' && $statusFilter === 'closed' => 'My closed cases',
            $scope === 'mine' && $statusFilter === 'resolved' => 'My resolved cases',
            $scope === 'mine' && $statusFilter === 'pending' => 'My pending cases',
            $scope === 'mine' && $statusFilter === 'in_progress' => 'My in-progress cases',
            $scope === 'mine' => 'My cases',
            $scope === 'assigned' && $statusFilter === 'open' => 'Assigned open cases',
            $scope === 'assigned' => 'Assigned to me',
            $statusFilter === 'new' && $request->boolean('unassigned') => 'New cases',
            $statusFilter === 'open' => 'Open case queue',
            default => $employeeView ? 'My cases' : 'Cases',
        };

        return view('helpdesk.index', [
            'title' => $pageTitle,
            'tickets' => $tickets,
            'departments' => Department::query()->orderBy('name')->get(),
            'scope' => $scope,
            'statusFilter' => $statusFilter,
            'employeeView' => $employeeView,
            'filters' => $request->only(['q', 'status', 'priority', 'department', 'scope', 'sort', 'direction']),
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
        $ticket->load(['requester', 'assignee', 'partners', 'department', 'category', 'asset.category', 'asset.location', 'application', 'histories.changer', 'attachments.uploader', 'duplicateOf']);
        $canSeeInternal = $request->user()->hasAnyPermission(['tickets.update', 'audit.view'])
            || $ticket->isPartner($request->user());
        $comments = $ticket->comments()->with(['author.roles:id,name'])->latest('updated_at')
            ->when(! $canSeeInternal, fn ($query) => $query->where('visibility', 'requester_visible'))
            ->get();

        return view('helpdesk.show', [
            'title' => $ticket->ticket_number,
            'ticket' => $ticket,
            'comments' => $comments,
            'canSeeInternal' => $canSeeInternal,
            'transitions' => $tickets->allowedTransitions($ticket, $request->user()),
            'canTakeOwnership' => $tickets->canTakeOwnership($ticket, $request->user()),
            'canTransferOwnership' => $tickets->canTransferOwnership($ticket, $request->user()),
            'isCasePartner' => $ticket->isPartner($request->user()),
            'technicians' => User::query()
                ->with(['roles:id,name', 'department:id,name,code'])
                ->where('status', 'active')
                ->when(
                    ! $request->user()->hasPermission('tickets.assign'),
                    fn ($query) => $query->whereIn('id', $ticket->partners->pluck('id')->all() ?: [0])
                )
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'job_title', 'department_id', 'last_seen_at', 'status']),
            'presence' => app(\App\Services\PresenceService::class),
            'partnerCandidates' => User::query()
                ->with(['roles:id,name', 'department:id,name,code'])
                ->where('status', 'active')
                ->whereNotIn('id', $ticket->partners->pluck('id')->all())
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'job_title', 'department_id']),
            'canTriage' => $request->user()->hasPermission('tickets.assign')
                && ! in_array($ticket->status, ['resolved', 'closed'], true),
            'canRemovePartners' => $request->user()->hasPermission('tickets.assign'),
        ]);
    }

    public function addPartner(Request $request, SupportTicket $ticket, \App\Services\NotificationService $notifications)
    {
        $this->visible($request, $ticket);
        $user = $request->user();
        abort_unless(
            $user->hasPermission('tickets.assign')
                || (int) $ticket->requester_id === (int) $user->id
                || ($ticket->assigned_to && (int) $ticket->assigned_to === (int) $user->id)
                || $ticket->isPartner($user),
            403
        );

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $partner = User::query()->whereKey($data['user_id'])->where('status', 'active')->firstOrFail();
        $notifications->syncPartners($ticket, [$partner->id], 'manual');
        $ticket->touch();
        $ticket->load('partners');
        app(AuditLogger::class)->log('ticket.partner.add', 'support_ticket', $ticket->id, [], [
            'partner_id' => $partner->id,
            'partner_name' => $partner->name,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $partner->name.' was added as a case partner.',
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'url' => route('people.show', $partner),
                    'source' => 'manual',
                ],
                'partners' => $this->partnerPayload($ticket),
                'activity' => $this->activityPayload($ticket, $request->user()),
            ]);
        }

        return back()->with('status', $partner->name.' was added as a case partner.');
    }

    public function removePartner(Request $request, SupportTicket $ticket, User $partner, \App\Services\NotificationService $notifications, AuditLogger $audit)
    {
        $this->visible($request, $ticket);
        abort_unless($request->user()->hasPermission('tickets.assign'), 403);

        if (! $ticket->isPartner($partner)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $partner->name.' is not a partner on this case.',
                ], 422);
            }

            return back()->withErrors(['partner' => $partner->name.' is not a partner on this case.']);
        }

        if ($ticket->assigned_to && (int) $ticket->assigned_to === (int) $partner->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'The case owner cannot be removed as a partner. Transfer ownership first.',
                ], 422);
            }

            return back()->withErrors(['partner' => 'The case owner cannot be removed as a partner. Transfer ownership first.']);
        }

        $notifications->removePartner($ticket, $partner);
        $ticket->touch();
        $audit->log('ticket.partner.remove', 'support_ticket', $ticket->id, [
            'partner_id' => $partner->id,
            'partner_name' => $partner->name,
        ], []);
        $ticket->load('partners');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $partner->name.' was removed from case partners.',
                'removed_partner_id' => $partner->id,
                'partners' => $this->partnerPayload($ticket),
                'activity' => $this->activityPayload($ticket, $request->user()),
            ]);
        }

        return back()->with('status', $partner->name.' was removed from case partners.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partnerPayload(SupportTicket $ticket): array
    {
        return $ticket->partners->map(fn (User $person) => [
            'id' => $person->id,
            'name' => $person->name,
            'url' => route('people.show', $person),
            'source' => $person->pivot->source ?? 'manual',
        ])->values()->all();
    }

    public function takeOwnership(Request $request, SupportTicket $ticket, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        $tickets->takeOwnership($ticket, $request->user());
        $ticket->refresh()->load('assignee');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'You now own '.$ticket->ticket_number.'.',
                'owner' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'url' => route('people.show', $request->user()),
                ],
                'assigned_to' => $request->user()->id,
                'status' => $ticket->status,
                'status_badge' => \App\Support\Labels::badge((string) $ticket->status),
                'activity' => $this->activityPayload($ticket, $request->user()),
            ]);
        }

        return back()->with('status', 'You now own '.$ticket->ticket_number.'.');
    }

    public function transfer(Request $request, SupportTicket $ticket, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignee = User::query()->whereKey($data['assigned_to'])->where('status', 'active')->firstOrFail();
        $tickets->assignTo(
            $ticket,
            $assignee,
            $request->user(),
            $data['note'] ?? $request->user()->name.' transferred ownership to '.$assignee->name.'.'
        );
        $ticket->refresh()->load(['assignee', 'partners']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $ticket->ticket_number.' was transferred to '.$assignee->name.'.',
                'owner' => [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'url' => route('people.show', $assignee),
                ],
                'assigned_to' => $assignee->id,
                'status' => $ticket->status,
                'status_badge' => \App\Support\Labels::badge((string) $ticket->status),
                'partners' => $ticket->partners->map(fn (User $person) => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'url' => route('people.show', $person),
                    'source' => $person->pivot->source ?? 'manual',
                ])->values()->all(),
                'activity' => $this->activityPayload($ticket, $request->user()),
            ]);
        }

        return back()->with('status', $ticket->ticket_number.' was transferred to '.$assignee->name.'.');
    }

    public function transition(Request $request, SupportTicket $ticket, TicketService $tickets)
    {
        $this->visible($request, $ticket);
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed'])],
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

        $canPostInternal = $request->user()->hasPermission('tickets.update')
            || $ticket->isPartner($request->user());
        if ($data['visibility'] === 'internal' && ! $canPostInternal) {
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
                'activity' => $this->activityPayload($ticket, $request->user()),
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

        $canPostInternal = $request->user()->hasPermission('tickets.update')
            || $ticket->isPartner($request->user());
        if ($data['visibility'] === 'internal' && ! $canPostInternal) {
            $data['visibility'] = 'requester_visible';
        }

        $entry = $tickets->updateComment($comment, $request->user(), $data['comment'], $data['visibility']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Note updated.',
                'comment' => $this->commentPayload($entry, $request->user()),
                'activity' => $this->activityPayload($ticket, $request->user()),
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
                'activity' => $this->activityPayload($ticket, $request->user()),
            ]);
        }

        return back()->with('status', 'Note deleted.');
    }

    public function activity(Request $request, SupportTicket $ticket)
    {
        $this->visible($request, $ticket);

        return response()->json([
            'ok' => true,
            'activity' => $this->activityPayload($ticket, $request->user()),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityPayload(SupportTicket $ticket, $user): array
    {
        $ticket->loadMissing(['histories.changer']);
        $canSeeInternal = $user->hasAnyPermission(['tickets.update', 'audit.view'])
            || $ticket->isPartner($user);
        $comments = $ticket->comments()->with(['author.roles:id,name'])->latest('updated_at')
            ->when(! $canSeeInternal, fn ($query) => $query->where('visibility', 'requester_visible'))
            ->get();

        return \App\Support\CaseActivity::forTicket($ticket, $comments)
            ->map(fn (array $item) => [
                'type' => $item['type'] ?? 'update',
                'label' => $item['label'] ?? 'Update',
                'badge' => $item['badge'] ?? 'update',
                'badge_html' => \App\Support\Labels::badge((string) ($item['badge'] ?? 'update')),
                'body' => $item['body'] ?? null,
                'html' => $item['html'] ?? null,
                'actor' => $item['actor'] ?? 'System',
                'actor_id' => $item['actor_id'] ?? null,
                'actor_url' => $item['actor_url'] ?? null,
                'at' => \App\Support\Format::date($item['at'] ?? null),
                'note_id' => $item['note_id'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function commentPayload(TicketComment $comment, $user): array
    {
        $author = $comment->author;

        return [
            'id' => $comment->id,
            'author' => $author?->name ?? 'User',
            'author_id' => $comment->user_id,
            'author_initials' => $author?->initials() ?? 'U',
            'author_role' => $author?->shortRoleLabel() ?? 'User',
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

    public function preview(Request $request, SupportTicket $ticket, TicketAttachment $attachment)
    {
        $this->visible($request, $ticket);
        abort_unless($attachment->support_ticket_id === $ticket->id, 404);
        abort_unless($attachment->isImage(), 404);

        $path = Storage::disk('local')->path($attachment->storage_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type ?: 'image/png',
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroyAttachment(Request $request, SupportTicket $ticket, TicketAttachment $attachment, AuditLogger $audit)
    {
        $this->visible($request, $ticket);
        abort_unless($attachment->support_ticket_id === $ticket->id, 404);
        abort_unless($request->user()->canDeleteTicketAttachment($attachment), 403);

        $path = $attachment->storage_path;
        $name = $attachment->original_name;
        $id = $attachment->id;

        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $attachment->delete();
        $audit->log('ticket.attachment.delete', 'support_ticket', $ticket->id, [
            'attachment_id' => $id,
            'original_name' => $name,
        ], []);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $name.' was deleted.',
                'attachment_id' => $id,
            ]);
        }

        return back()->with('status', $name.' was deleted.');
    }

    private function visible(Request $request, SupportTicket $ticket): void
    {
        // Any signed-in user may open a case. Ownership and edits stay controlled separately.
        abort_unless($request->user() !== null, 403);
    }
}
