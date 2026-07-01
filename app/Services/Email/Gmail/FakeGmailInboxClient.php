<?php

namespace App\Services\Email\Gmail;

use Illuminate\Support\Carbon;

/**
 * Offline stand-in for the Gmail inbox: returns deterministic BidPrime-style
 * fixture emails (an HTML daily digest, an HTML single alert, and a plain-text
 * alert) so the whole ingestion pipeline runs and is tested without live IMAP.
 *
 * Used automatically whenever GMAIL_INGEST_ENABLED is off or no App Password is
 * configured. Replaced by ImapGmailInboxClient once real credentials are set.
 */
class FakeGmailInboxClient implements GmailInboxClient
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function label(): string
    {
        return 'fake';
    }

    /** @return array<int,GmailMessage> */
    public function fetch(array $criteria = []): array
    {
        $messages = [
            $this->digestEmail(),
            $this->singleAlertEmail(),
            $this->plainTextEmail(),
        ];

        $limit = $criteria['limit'] ?? null;

        return $limit ? array_slice($messages, 0, (int) $limit) : $messages;
    }

    private function digestEmail(): GmailMessage
    {
        $html = <<<'HTML'
<html><body>
<h2>BidPrime Daily Bid Alert</h2>
<p>2 new opportunities matched your saved searches.</p>
<table class="bid"><tr><td>
  <a href="https://app.bidprime.com/bid/100234">Seismic Monitoring System Upgrade — Statewide</a><br>
  Agency: California Department of Transportation<br>
  Solicitation #: RFP-2026-0042<br>
  Due Date: 08/15/2026<br>
  Posted: 06/20/2026<br>
  Location: Sacramento, CA<br>
  NAICS: 334513<br>
  Set-Aside: Small Business<br>
  Estimated Value: $1,250,000<br>
  Description: Supply and install a statewide seismic and structural health monitoring network with triaxial accelerometers and centralized data acquisition.<br>
  <a href="https://caltrans.ca.gov/solicitations/rfp-2026-0042">View original solicitation</a>
</td></tr></table>
<table class="bid"><tr><td>
  <a href="https://app.bidprime.com/bid/100235">Vibration Monitoring &amp; Accelerometer Array — Bridge Retrofit</a><br>
  Agency: Texas Department of Transportation<br>
  Solicitation #: IFB-2026-TX-778<br>
  Due Date: 07/30/2026<br>
  Posted: 06/22/2026<br>
  Location: Austin, TX<br>
  NAICS: 541330<br>
  Estimated Value: $480,000<br>
  Description: Provide vibration monitoring instrumentation and accelerometers for a bridge structural retrofit project.<br>
  <a href="https://txdot.gov/bids/ifb-2026-tx-778">View original solicitation</a>
</td></tr></table>
</body></html>
HTML;

        return new GmailMessage(
            messageId: '<bidprime-digest-2026-06-29@bidprime.com>',
            uid: '5001',
            threadId: 'thread-9001',
            fromEmail: 'alerts@bidprime.com',
            fromName: 'BidPrime Alerts',
            subject: 'BidPrime Daily Bid Alert — 2 new opportunities',
            date: Carbon::parse('2026-06-29 06:02:00'),
            html: $html,
            text: null,
        );
    }

    private function singleAlertEmail(): GmailMessage
    {
        $html = <<<'HTML'
<html><body>
<h2>BidPrime Keyword Alert: "shake table"</h2>
<div class="opportunity">
  <a href="https://app.bidprime.com/bid/100777">Shake Table Procurement for University Seismic Lab</a><br>
  Agency: University of Nevada, Reno<br>
  Bid #: UNR-2026-SHK-09<br>
  Due Date: 09/01/2026<br>
  Posted: 06/25/2026<br>
  Location: Reno, NV<br>
  NAICS: 333999<br>
  Estimated Value: $900,000<br>
  Description: Procurement of a six-degree-of-freedom shake table / seismic simulator with servo-hydraulic actuators for structural testing.<br>
  <a href="https://nevada.bonfirehub.com/projects/unr-2026-shk-09">View original solicitation</a>
</div>
</body></html>
HTML;

        return new GmailMessage(
            messageId: '<bidprime-alert-shaketable-2026-06-25@bidprime.com>',
            uid: '5002',
            threadId: 'thread-9002',
            fromEmail: 'alerts@bidprime.com',
            fromName: 'BidPrime Alerts',
            subject: 'BidPrime Keyword Alert: shake table',
            date: Carbon::parse('2026-06-25 06:05:00'),
            html: $html,
            text: null,
        );
    }

    private function plainTextEmail(): GmailMessage
    {
        $text = <<<'TXT'
BidPrime Bid Alert

Title: Early Warning Seismic Sensor Network — Pilot
Agency: City of Los Angeles
Bid #: LA-EQ-2026-12
Due Date: 10/05/2026
Posted: 06/26/2026
Location: Los Angeles, CA
NAICS: 334511
Estimated Value: $640,000
Description: Install an early earthquake warning sensor network including seismometers and instrumentation.
View on BidPrime: https://app.bidprime.com/bid/100888
TXT;

        return new GmailMessage(
            messageId: '<bidprime-text-2026-06-26@bidprime.com>',
            uid: '5003',
            threadId: 'thread-9003',
            fromEmail: 'alerts@bidprime.com',
            fromName: 'BidPrime Alerts',
            subject: 'BidPrime Bid Alert',
            date: Carbon::parse('2026-06-26 06:08:00'),
            html: null,
            text: $text,
        );
    }
}
