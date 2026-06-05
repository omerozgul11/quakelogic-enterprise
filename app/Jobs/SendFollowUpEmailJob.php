<?php

namespace App\Jobs;

use App\Models\FollowUp;
use App\Services\Email\SmtpEmailProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFollowUpEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $followUpId)
    {
        $this->onQueue('emails');
    }

    public function handle(SmtpEmailProvider $emailProvider): void
    {
        $followUp = FollowUp::with(['contact', 'assignedTo'])->find($this->followUpId);

        if (!$followUp || $followUp->status === 'sent') {
            return;
        }

        $recipient = $followUp->contact?->email ?? $followUp->assignedTo?->email;

        if (!$recipient) {
            Log::warning('No recipient for follow-up email', ['follow_up_id' => $this->followUpId]);
            return;
        }

        $sent = $emailProvider->send(
            $recipient,
            $followUp->subject,
            $followUp->message ?? ''
        );

        if ($sent) {
            $followUp->update(['status' => 'sent', 'sent_at' => now()]);
        } else {
            Log::error('Failed to send follow-up email', ['follow_up_id' => $this->followUpId]);
        }
    }
}
