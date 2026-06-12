<?php

namespace App\Services\Mail;

use App\Models\EmailAccount;
use App\Models\User;

/**
 * Connect, test and disconnect a user's per-user SMTP work mailbox. Shared by
 * the self-service Settings page and the admin user panel so an administrator
 * can set up (or repair) a teammate's outgoing email on their behalf.
 */
class MailboxConnectionService
{
    /** Shape the connected-mailbox state for a page payload (never the password). */
    public function state(?EmailAccount $account): array
    {
        return [
            'connected' => (bool) $account?->isConnected(),
            'provider' => $account?->provider,
            'email' => $account?->email,
            'from_name' => $account?->from_name,
            'smtp_host' => $account?->smtp_host,
            'smtp_port' => $account?->smtp_port,
            'smtp_encryption' => $account?->smtp_encryption ?? 'tls',
            'smtp_username' => $account?->smtp_username,
        ];
    }

    /** The user's SMTP mailbox, if one has been set up. */
    public function mailbox(User $user): ?EmailAccount
    {
        return EmailAccount::where('user_id', $user->id)->where('provider', 'smtp')->first();
    }

    /**
     * Connect (or update) the user's SMTP mailbox from already-validated input.
     * The app password is required to connect for the first time and optional on
     * later edits (left blank keeps the stored one). Returns null when a
     * first-time connection is missing its password so the caller can surface a
     * validation error.
     *
     * @param  array<string,mixed>  $validated
     */
    public function connect(User $user, array $validated): ?EmailAccount
    {
        $existing = $this->mailbox($user);

        if (!$existing && empty($validated['smtp_password'])) {
            return null;
        }

        $data = [
            'email' => $validated['email'],
            'from_name' => ($validated['from_name'] ?? '') ?: $user->name,
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'],
            'smtp_encryption' => ($validated['smtp_encryption'] ?? 'tls') === 'none' ? null : ($validated['smtp_encryption'] ?? 'tls'),
            'smtp_username' => ($validated['smtp_username'] ?? '') ?: $validated['email'],
            'connected_at' => now(),
        ];
        if (!empty($validated['smtp_password'])) {
            $data['smtp_password'] = $validated['smtp_password'];
        }

        return EmailAccount::updateOrCreate(['user_id' => $user->id, 'provider' => 'smtp'], $data);
    }

    /**
     * Send a verification email to the mailbox owner's own address. Returns
     * false when the mailbox is not (yet) connected or the send fails.
     */
    public function test(User $user, ?string $connectedBy = null): bool
    {
        $account = $this->mailbox($user);
        if (!$account || !$account->isConnected()) {
            return false;
        }

        $body = "This is a test from QuakeLogic Proposals confirming your work email is connected and able to send."
            . ($connectedBy ? "\n\nIt was set up for you by {$connectedBy}." : '')
            . "\n\nIf you received this, you're all set.";

        return (new SmtpMailGateway($account))->send(
            $user->email,
            $user->name,
            'QuakeLogic — work email test',
            $body,
        );
    }

    public function disconnect(User $user): void
    {
        EmailAccount::where('user_id', $user->id)->where('provider', 'smtp')->delete();
    }
}
