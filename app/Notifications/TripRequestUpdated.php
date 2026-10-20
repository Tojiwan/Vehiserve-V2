<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripRequestUpdated extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
    ) {}

    /**
     * Demo accounts are in-app only; email is reserved for owner-controlled accounts.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ($notifiable->is_demo ?? true) ? ['database'] : ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        // Filament's bell only lists rows whose data carries format "filament";
        // the same payload keeps title/body for the portal list.
        return [
            'format' => 'filament',
            'duration' => 'persistent',
            'title' => e($this->title),
            // Filament renders HTML; requester-supplied text must not become links or markup.
            'body' => e($this->message),
            'message' => $this->message,
            'url' => $this->url,
            'actions' => [
                ['name' => 'view', 'label' => 'View request', 'url' => $this->url],
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('View request', $this->url)
            ->line('Automated message from VEHISERVE, an independent portfolio recreation of a university motor pool system.');
    }
}
