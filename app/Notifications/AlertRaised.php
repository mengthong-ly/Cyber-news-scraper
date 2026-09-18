<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertRaised extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $item = $this->alert->item;

        return (new MailMessage)
            ->subject("[Cyber Watch] Severity {$this->alert->severity}: {$item->displayTitle()}")
            ->line($this->alert->reason)
            ->line($item->summary_en ?? $item->excerpt ?? '')
            ->action('Open alerts', route('alerts.index'))
            ->line('Source: '.($item->publisher ?? parse_url($item->url, PHP_URL_HOST)));
    }
}
