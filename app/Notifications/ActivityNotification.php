<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lightweight in-app alert (new proposals, opportunities, assignments, coworker
 * messages, etc.). Stored in the database for the bell/Inbox. When the sender
 * opts in via `email => true` (reminders + changes to a proposal the recipient
 * is attached to), it is also emailed to the recipient's connected work email
 * (see User::routeNotificationForMail), sent from the platform address. Queued
 * so wide fan-outs never block the web request.
 */
class ActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{type:string,title:string,message?:string,url?:string,icon?:string,email?:bool}  $payload
     */
    public function __construct(private readonly array $payload) {}

    /** Run database + mail delivery on the dedicated notifications queue. */
    public function viaQueues(): array
    {
        return ['database' => 'notifications', 'mail' => 'notifications'];
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Email is opt-IN per notification (payload `email => true`). Only
        // reminders and changes to a proposal the recipient is attached to set
        // it; daily summaries email separately via their command. Everything
        // else stays in-app only. A per-user email opt-out still wins.
        $prefs = $notifiable->notification_preferences['channels'] ?? [];
        $wantsEmail = ($this->payload['email'] ?? false) === true && ($prefs['email'] ?? true) !== false;
        if ($wantsEmail) {
            $channels[] = 'mail';
        }

        return $channels;
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

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->payload['title'] ?? 'Notification';
        $message = $this->payload['message'] ?? '';

        $mail = (new MailMessage())
            ->subject($title)
            ->greeting($title);

        if ($message !== '') {
            $mail->line($message);
        }
        if (! empty($this->payload['url'])) {
            $mail->action('Open in QuakeLogic', $this->payload['url']);
        }

        return $mail->line('You\'re receiving this because of your QuakeLogic notification settings.');
    }
}
