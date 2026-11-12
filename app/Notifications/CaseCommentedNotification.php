<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseCommentedNotification extends Notification
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
        $label = $this->comment->visibility === 'internal' ? 'internal note' : 'note';

        return (new MailMessage)
            ->subject('New '.$label.' on '.$this->ticket->ticket_number)
            ->line($this->author->name.' added a '.$label.' to '.$this->ticket->ticket_number.'.')
            ->line(str($this->comment->comment)->limit(160))
            ->action('Open case', route('helpdesk.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $internal = $this->comment->visibility === 'internal';

        return [
            'kind' => $internal ? 'case_internal_note' : 'case_commented',
            'title' => $internal ? 'New internal note' : 'New case note',
            'body' => $this->ticket->ticket_number.' · '.$this->author->name,
            'url' => route('helpdesk.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'comment_id' => $this->comment->id,
        ];
    }
}
