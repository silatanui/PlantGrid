<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
        public User $assignedBy
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
            ->subject('Case assigned: '.$this->ticket->ticket_number)
            ->line($this->assignedBy->name.' assigned '.$this->ticket->ticket_number.' to you.')
            ->line($this->ticket->subject)
            ->action('Open case', route('helpdesk.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'case_assigned',
            'title' => 'Case assigned to you',
            'body' => $this->ticket->ticket_number.' · '.$this->ticket->subject,
            'url' => route('helpdesk.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
        ];
    }
}
