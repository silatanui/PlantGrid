<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\User;
use App\Support\Format;
use App\Support\RichText;
use Illuminate\Http\Request;

class LiveInboxController extends Controller
{
    public function feed(Request $request)
    {
        $user = $request->user();

        $notifications = $user->unreadNotifications()->latest()->limit(8)->get();
        $messages = DirectMessage::query()
            ->with('sender:id,name')
            ->unreadFor($user)
            ->latest()
            ->limit(8)
            ->get();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'notifications' => [
                'count' => $user->unreadNotifications()->count(),
                'items' => $notifications->map(fn ($note) => [
                    'id' => $note->id,
                    'title' => data_get($note->data, 'title', 'Update'),
                    'body' => data_get($note->data, 'body', ''),
                    'url' => route('notifications.read', $note->id),
                    'created_at' => optional($note->created_at)?->toIso8601String(),
                ])->values(),
            ],
            'messages' => [
                'count' => DirectMessage::query()->unreadFor($user)->count(),
                'items' => $messages->map(fn (DirectMessage $message) => [
                    'id' => $message->id,
                    'subject' => $message->subject,
                    'sender' => $message->sender->name ?? 'Someone',
                    'url' => route('messages.show', $message),
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function thread(Request $request, User $person)
    {
        $user = $request->user();
        abort_if((int) $person->id === (int) $user->id, 404);

        $thread = DirectMessage::query()
            ->where(function ($query) use ($user, $person) {
                $query->where(function ($inner) use ($user, $person) {
                    $inner->where('sender_id', $user->id)->where('recipient_id', $person->id);
                })->orWhere(function ($inner) use ($user, $person) {
                    $inner->where('sender_id', $person->id)->where('recipient_id', $user->id);
                });
            })
            ->with(['sender:id,name', 'recipient:id,name'])
            ->orderBy('created_at')
            ->get();

        $thread->where('recipient_id', $user->id)->whereNull('read_at')->each(function (DirectMessage $item) {
            $item->forceFill(['read_at' => now()])->save();
        });

        return response()->json([
            'counterpart_id' => $person->id,
            'count' => $thread->count(),
            'items' => $thread->map(fn (DirectMessage $item) => [
                'id' => $item->id,
                'mine' => (int) $item->sender_id === (int) $user->id,
                'sender' => (int) $item->sender_id === (int) $user->id ? 'You' : ($item->sender->name ?? 'Someone'),
                'subject' => $item->subject,
                'body_html' => RichText::render($item->body),
                'created_label' => $item->created_at?->format('d M Y H:i') ?? Format::date($item->created_at),
            ])->values(),
        ]);
    }
}
