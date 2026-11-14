<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $folder = $request->string('folder')->toString() === 'sent' ? 'sent' : 'inbox';

        $messages = DirectMessage::query()
            ->with(['sender:id,name,email', 'recipient:id,name,email'])
            ->when($folder === 'sent', fn ($query) => $query->where('sender_id', $user->id))
            ->when($folder === 'inbox', fn ($query) => $query->where('recipient_id', $user->id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('messages.index', [
            'title' => $folder === 'sent' ? 'Sent messages' : 'Messages',
            'folder' => $folder,
            'messages' => $messages,
        ]);
    }

    public function show(DirectMessage $message): View
    {
        $user = auth()->user();
        abort_unless(in_array($user->id, [$message->sender_id, $message->recipient_id], true), 403);

        $message->load(['sender.roles', 'sender.department', 'recipient.roles', 'recipient.department']);

        if ($message->isUnreadFor($user)) {
            $message->forceFill(['read_at' => now()])->save();
        }

        $counterpart = $message->counterpart($user);

        $thread = DirectMessage::query()
            ->where(function ($query) use ($user, $counterpart) {
                $query->where(function ($inner) use ($user, $counterpart) {
                    $inner->where('sender_id', $user->id)->where('recipient_id', $counterpart->id);
                })->orWhere(function ($inner) use ($user, $counterpart) {
                    $inner->where('sender_id', $counterpart->id)->where('recipient_id', $user->id);
                });
            })
            ->with(['sender:id,name', 'recipient:id,name'])
            ->orderBy('created_at')
            ->get();

        $thread->where('recipient_id', $user->id)->whereNull('read_at')->each(function (DirectMessage $item) {
            $item->forceFill(['read_at' => now()])->save();
        });

        return view('messages.show', [
            'title' => $message->subject,
            'message' => $message,
            'counterpart' => $counterpart,
            'thread' => $thread,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'min:2', 'max:180'],
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        abort_if((int) $data['recipient_id'] === auth()->id(), 422, 'You cannot message yourself.');

        $recipient = User::query()->findOrFail($data['recipient_id']);
        abort_if($recipient->status !== 'active', 404);

        $message = DirectMessage::query()->create([
            'sender_id' => auth()->id(),
            'recipient_id' => $recipient->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        return redirect()
            ->route('messages.show', $message)
            ->with('status', 'Message sent.');
    }

    public function destroy(Request $request, DirectMessage $message): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->id, [$message->sender_id, $message->recipient_id], true), 403);

        $folder = $message->sender_id === $user->id ? 'sent' : 'inbox';
        $message->delete();

        return redirect()
            ->route('messages.index', ['folder' => $folder])
            ->with('status', 'Message deleted permanently.');
    }
}
