<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\WorkerPerformanceService;
use App\Support\CaseActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeopleController extends Controller
{
    public function show(User $person): View
    {
        abort_if($person->status !== 'active' && $person->id !== auth()->id(), 404);

        $person->load(['roles:id,name', 'department:id,name']);

        $openCases = SupportTicket::query()
            ->where('assigned_to', $person->id)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('reported_at')
            ->limit(8)
            ->get(['id', 'ticket_number', 'subject', 'status', 'priority']);

        $thread = DirectMessage::query()
            ->where(function ($query) use ($person) {
                $query->where(function ($inner) use ($person) {
                    $inner->where('sender_id', auth()->id())->where('recipient_id', $person->id);
                })->orWhere(function ($inner) use ($person) {
                    $inner->where('sender_id', $person->id)->where('recipient_id', auth()->id());
                });
            })
            ->with(['sender:id,name', 'recipient:id,name'])
            ->latest()
            ->limit(12)
            ->get()
            ->sortBy('created_at')
            ->values();

        return view('people.show', [
            'title' => $person->name,
            'person' => $person,
            'openCases' => $openCases,
            'thread' => $thread,
        ]);
    }

    public function performance(Request $request, User $person, WorkerPerformanceService $performance): View
    {
        abort_if($person->status !== 'active' && $person->id !== auth()->id(), 404);

        $person->load(['roles:id,name', 'department:id,name']);

        $snapshot = $performance->forPerson($person, $request->only(['from', 'to']));
        $presets = $performance->rangePresets();
        $activeRange = collect($presets)->first(function (array $preset) use ($snapshot) {
            return $preset['from'] === $snapshot['from']->toDateString()
                && $preset['to'] === $snapshot['to']->toDateString();
        })['id'] ?? 'custom';

        $partnerCases = SupportTicket::query()
            ->with(['assignee:id,name'])
            ->open()
            ->whereHas('partners', fn ($partners) => $partners->where('users.id', $person->id))
            ->where(function ($query) use ($person) {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', '!=', $person->id);
            })
            ->latest('reported_at')
            ->limit(15)
            ->get(['id', 'ticket_number', 'subject', 'status', 'priority', 'assigned_to']);

        $requestedCases = SupportTicket::query()
            ->open()
            ->where('requester_id', $person->id)
            ->latest('reported_at')
            ->limit(15)
            ->get(['id', 'ticket_number', 'subject', 'status', 'priority']);

        $notes = TicketComment::query()
            ->with(['ticket:id,ticket_number,subject'])
            ->where('user_id', $person->id)
            ->whereBetween('created_at', [$snapshot['from'], $snapshot['to']])
            ->latest()
            ->limit(20)
            ->get();

        $activity = CaseActivity::forUserRail($person, 15);

        return view('people.performance', [
            'title' => $person->name.' · Performance',
            'person' => $person,
            'snapshot' => $snapshot,
            'rangePresets' => $presets,
            'activeRange' => $activeRange,
            'partnerCases' => $partnerCases,
            'requestedCases' => $requestedCases,
            'notes' => $notes,
            'activity' => $activity,
            'canViewTeamBoard' => $request->user()->hasPermission('reports.view'),
        ]);
    }

    public function updateContact(Request $request, User $person): RedirectResponse
    {
        abort_unless((int) $person->id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'extension' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:24'],
            'country' => ['nullable', 'string', 'max:80'],
        ]);

        $person->update($data);

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Your contact details were saved.');
    }

    public function message(Request $request, User $person): RedirectResponse
    {
        abort_if($person->id === auth()->id(), 422, 'You cannot message yourself.');
        abort_if($person->status !== 'active', 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'min:2', 'max:180'],
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $message = DirectMessage::query()->create([
            'sender_id' => auth()->id(),
            'recipient_id' => $person->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        return redirect()
            ->route('messages.show', $message)
            ->with('status', 'Message sent to '.$person->name.'.');
    }
}
