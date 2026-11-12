<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseMentionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
        public TicketComment $comment,
        public User $author
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were mentioned on '.$this->ticket->ticket_number)
            ->line($this->author->name.' mentioned you on '.$this->ticket->ticket_number.'.')
            ->line(str($this->comment->comment)->limit(160))
            ->action('Open case', route('helpdesk.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $kind = $this->comment->visibility === 'internal' ? 'internal note' : 'case note';

        return [
            'kind' => 'case_mentioned',
            'title' => 'You were mentioned',
            'body' => $this->ticket->ticket_number.' · '.$this->author->name.' · '.$kind,
            'url' => route('helpdesk.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'comment_id' => $this->comment->id,
        ];
    }
}
