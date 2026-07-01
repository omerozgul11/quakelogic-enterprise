<?php

namespace Tests\Unit\BidSources;

use App\Services\BidSources\BidPrime\BidPrimeEmailParser;
use App\Services\Email\Gmail\FakeGmailInboxClient;
use App\Services\Email\Gmail\GmailMessage;
use PHPUnit\Framework\TestCase;

class BidPrimeEmailParserTest extends TestCase
{
    private function parser(): BidPrimeEmailParser
    {
        return new BidPrimeEmailParser();
    }

    public function test_parses_html_digest_into_multiple_opportunities(): void
    {
        $messages = (new FakeGmailInboxClient())->fetch();
        $digest = $messages[0];

        $dtos = $this->parser()->extractOpportunities($digest);

        $this->assertCount(2, $dtos);
        $first = $dtos[0];
        $this->assertSame('bp-100234', $first->externalId);
        $this->assertSame('bidprime', $first->source);
        $this->assertSame('RFP-2026-0042', $first->solicitationNumber);
        $this->assertSame('California Department of Transportation', $first->agencyName);
        $this->assertSame('334513', $first->naicsCode);
        $this->assertSame('Small Business', $first->setAsideType);
        $this->assertSame(1250000.0, $first->estimatedValue);
        $this->assertSame('Sacramento', $first->placeOfPerformanceCity);
        $this->assertSame('CA', $first->placeOfPerformanceState);
        $this->assertSame('2026-08-15', $first->dueDate?->format('Y-m-d'));
        $this->assertSame('2026-06-20', $first->postedDate?->format('Y-m-d'));
        $this->assertStringContainsString('bidprime.com/bid/100234', (string) $first->sourceUrl);
    }

    public function test_parses_single_html_alert(): void
    {
        $message = (new FakeGmailInboxClient())->fetch()[1];

        $dtos = $this->parser()->extractOpportunities($message);

        $this->assertCount(1, $dtos);
        $this->assertSame('bp-100777', $dtos[0]->externalId);
        $this->assertStringContainsString('Shake Table', $dtos[0]->title);
        $this->assertSame('2026-09-01', $dtos[0]->dueDate?->format('Y-m-d'));
    }

    public function test_parses_plain_text_email(): void
    {
        $message = (new FakeGmailInboxClient())->fetch()[2];

        $dtos = $this->parser()->extractOpportunities($message);

        $this->assertCount(1, $dtos);
        $this->assertSame('bp-100888', $dtos[0]->externalId);
        $this->assertSame('Early Warning Seismic Sensor Network — Pilot', $dtos[0]->title);
        $this->assertSame('Los Angeles', $dtos[0]->placeOfPerformanceCity);
        $this->assertSame('CA', $dtos[0]->placeOfPerformanceState);
    }

    public function test_unparseable_email_returns_no_opportunities_without_throwing(): void
    {
        $junk = new GmailMessage(
            messageId: '<junk@example.com>',
            subject: 'Newsletter',
            html: '<html><body><p>Thanks for subscribing. Nothing to bid here.</p></body></html>',
        );

        $this->assertSame([], $this->parser()->extractOpportunities($junk));
    }
}
