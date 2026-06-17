<?php

namespace App\Notifications;

use App\Models\ProposalMailing;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell) alert when a mailed proposal's UPS shipment changes state —
 * out for delivery, delivered (on time / late), or an exception. Email is a
 * later addition (Phase 3); this uses the database channel only.
 */
class MailingStatusChanged extends Notification
{
    public function __construct(
        public ProposalMailing $mailing,
        public string $title,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shipment',
            'title' => $this->title,
            'message' => $this->message,
            'url' => '/shipments/mailings/'.$this->mailing->ulid,
            'icon' => 'truck',
        ];
    }
}
