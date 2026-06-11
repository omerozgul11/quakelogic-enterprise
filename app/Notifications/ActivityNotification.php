<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Lightweight database notification used for in-app alerts (new proposals,
 * opportunities, assignments, etc.). The payload is intentionally generic so a
 * single class can render every alert type in the inbox and bell dropdown.
 */
class ActivityNotification extends Notification
{
    /**
     * @param  array{type:string,title:string,message?:string,url?:string,icon?:string}  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->payload['type'] ?? 'info',
            'title' => $this->payload['title'] ?? 'Notification',
            'message' => $this->payload['message'] ?? null,
            'url' => $this->payload['url'] ?? null,
            'icon' => $this->payload['icon'] ?? 'bell',
        ];
    }
}
