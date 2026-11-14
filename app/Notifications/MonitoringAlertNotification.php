<?php

namespace App\Notifications;

use App\Models\MonitoringAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MonitoringAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MonitoringAlert $alert,
        public string $scenario = 'outage',
        public string $hostname = '',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $host = $this->hostname !== '' ? $this->hostname : 'plant host';
        $title = $this->scenario === 'outage'
            ? 'Critical monitoring outage'
            : 'Monitoring instability';

        return [
            'kind' => 'monitoring_'.$this->scenario,
            'title' => $title,
            'body' => $this->alert->message ?: ('Simulated '.$this->scenario.' on '.$host),
            'url' => route('monitoring.index', ['target' => $this->alert->target_type.':'.$this->alert->target_id]),
            'alert_id' => $this->alert->id,
            'severity' => $this->alert->severity,
            'scenario' => $this->scenario,
            'hostname' => $host,
        ];
    }
}
