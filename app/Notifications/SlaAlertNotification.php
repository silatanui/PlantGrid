<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
        public string $level
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
        $title = $this->level === 'breached' ? 'SLA breached' : 'SLA at risk';

        return (new MailMessage)
            ->subject($title.': '.$this->ticket->ticket_number)
            ->line($title.' for '.$this->ticket->ticket_number.'.')
            ->line($this->ticket->subject)
            ->line('SLA due: '.optional($this->ticket->sla_due_at)->format('d M Y, H:i'))
            ->action('Open case', route('helpdesk.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'sla_'.$this->level,
            'title' => $this->level === 'breached' ? 'SLA breached' : 'SLA at risk',
            'body' => $this->ticket->ticket_number.' · '.$this->ticket->subject,
            'url' => route('helpdesk.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'level' => $this->level,
        ];
    }
}
