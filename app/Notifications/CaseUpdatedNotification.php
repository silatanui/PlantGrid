<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
        public User $actor,
        public string $previousStatus,
        public string $newStatus
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
            ->subject('Case update: '.$this->ticket->ticket_number)
            ->line($this->actor->name.' updated '.$this->ticket->ticket_number.'.')
            ->line('Status: '.str_replace('_', ' ', $this->previousStatus).' → '.str_replace('_', ' ', $this->newStatus))
            ->action('Open case', route('helpdesk.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'case_updated',
            'title' => 'Case status updated',
            'body' => $this->ticket->ticket_number.' · '.str_replace('_', ' ', $this->previousStatus).' → '.str_replace('_', ' ', $this->newStatus),
            'url' => route('helpdesk.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
        ];
    }
}
