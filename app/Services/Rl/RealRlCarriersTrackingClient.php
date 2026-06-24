<?php

namespace App\Services\Rl;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingException;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live R+L Carriers (LTL freight) tracking by PRO number. Two paths, picked
 * automatically:
 *
 *   1. Official REST API (api.rlc.com/ShipmentTracing) when an API key is
 *      configured (services.rlcarriers.api_key). This is the robust, documented
 *      contract (GetShipmentTracingResponse → Shipments[].StatusHistory[]) and is
 *      the recommended way to run in production. The key is free to R+L account
 *      holders (MyRLC → API).
 *
 *   2. Keyless public website scrape otherwise — R+L's tracing results page
 *      (no reCAPTCHA, unlike J.B. Hunt) renders the result and embeds it as JSON
 *      in the page. We parse that defensively and CONSERVATIVELY: a "no records"
 *      response is reported as pending (not an error), and a result is only marked
 *      Delivered when a scan/status string actually says so — we never fabricate a
 *      status. If the page can't be confidently parsed, we throw so the shipment
 *      stays on its current (e.g. manually-set) status rather than being clobbered.
 */
class RealRlCarriersTrackingClient implements CarrierTrackingClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $apiBaseUrl,
        private readonly string $webBaseUrl,
    ) {}

    public function track(string $trackingNumber): TrackingResult
    {
        $pro = trim($trackingNumber);

        return $this->apiKey
            ? $this->trackViaApi($pro)
            : $this->trackViaWebsite($pro);
    }

    // ── Official REST API ───────────────────────────────────────────────────

    private function trackViaApi(string $pro): TrackingResult
    {
        try {
            $response = Http::withHeaders(['apiKey' => $this->apiKey])
                ->acceptJson()
                ->connectTimeout(8)
                ->timeout(20)
                ->get(rtrim($this->apiBaseUrl, '/').'/ShipmentTracing', [
                    'request.traceNumbers' => $pro,
                    'request.traceType' => 'PRO',
                ]);

            if (in_array($response->status(), [401, 403], true)) {
                throw new TrackingException("R+L API key was rejected ({$response->status()}). Check RL_API_KEY.");
            }
            if ($response->serverError()) {
                throw new TrackingException("R+L tracking request failed ({$response->status()}) for {$pro}.");
            }
            if ($response->failed()) {
                return $this->pending('Awaiting first R+L scan.');
            }

            $body = $response->json() ?? [];
            $shipment = data_get($body, 'Shipments.0');
            if (! is_array($shipment)) {
                // No records yet (or not an R+L PRO) — pending, not an error.
                $msg = (string) (data_get($body, 'Errors.0') ?? data_get($body, 'Messages.0') ?? 'Awaiting first R+L scan.');

                return $this->pending($msg);
            }

            return $this->fromApiShipment($shipment);
        } catch (TrackingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TrackingException("R+L tracking error for {$pro}: {$e->getMessage()}", previous: $e);
        }
    }

    /** @param array<string, mixed> $s */
    private function fromApiShipment(array $s): TrackingResult
    {
        $events = [];
        foreach ((array) data_get($s, 'StatusHistory', []) as $h) {
            $desc = (string) (data_get($h, 'Description') ?: 'Update');
            $events[] = new TrackingEvent(
                $this->codeFor($desc),
                $desc,
                $this->location(data_get($h, 'City'), data_get($h, 'State')),
                $this->dateTime(data_get($h, 'Date'), data_get($h, 'Time')),
            );
        }
        usort($events, fn ($a, $b) => $b->occurredAt <=> $a->occurredAt);

        $statusText = trim((string) data_get($s, 'LongStatus').' '.(string) data_get($s, 'ShortStatus'));
        $status = $this->resolveStatus($statusText, $events);

        $scheduled = $this->date(data_get($s, 'EstimatedDelivery'));
        $deliveredAt = null;
        if ($status === MailingStatus::Delivered) {
            $deliveredAt = $this->dateTime(data_get($s, 'DeliveryDate.Date'), data_get($s, 'DeliveryDate.Time'))
                ?? ($events[0]->occurredAt ?? Carbon::now());
        }

        return new TrackingResult(
            $status,
            (string) (data_get($s, 'ShortStatus') ?: null) ?: null,
            $statusText ?: null,
            $scheduled,
            $deliveredAt,
            null,
            null,
            $events,
        );
    }

    // ── Keyless website scrape ──────────────────────────────────────────────

    private function trackViaWebsite(string $pro): TrackingResult
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; QuakeLogicShipments/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->connectTimeout(8)
                ->timeout(25)
                ->get(rtrim($this->webBaseUrl, '/').'/freight/shipping/shipment-tracing', [
                    'pro' => $pro,
                    'docType' => 'PRO',
                    'source' => 'web',
                ]);

            if (! $response->successful()) {
                throw new TrackingException("R+L tracking page failed (HTTP {$response->status()}) for {$pro}.");
            }

            $html = $response->body();
            $record = $this->extractEmbeddedRecord($html);

            // "No records found" (aged out / wrong number) → pending, not an error.
            if ($this->noRecords($html) || ($record !== null && $this->recordIsEmpty($record))) {
                return $this->pending('No records found on R+L for this PRO yet.');
            }

            if ($record !== null) {
                $parsed = $this->fromWebsiteRecord($record);
                if ($parsed !== null) {
                    return $parsed;
                }
            }

            // We reached a populated page we couldn't confidently parse. Don't
            // guess a status — leave the shipment as-is for a manual update.
            throw new TrackingException(
                "Couldn't read R+L tracking for {$pro} from the page. Update its status by hand, or add an R+L API key (RL_API_KEY) for reliable sync."
            );
        } catch (TrackingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TrackingException("R+L tracking error for {$pro}: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * R+L's results page embeds the traced shipment as an HTML-entity-encoded JSON
     * array in a hidden input, e.g. value="[{&quot;Head&quot;:...,&quot;History&quot;:[...]}]".
     *
     * @return array<string, mixed>|null
     */
    private function extractEmbeddedRecord(string $html): ?array
    {
        if (! preg_match_all('/value="(\[\{(?:[^"\\\\]|\\\\.)*\}\])"/s', $html, $m)) {
            return null;
        }

        foreach ($m[1] as $raw) {
            $json = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
            $decoded = json_decode($json, true);
            if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])
                && (array_key_exists('History', $decoded[0]) || array_key_exists('Errors', $decoded[0]) || array_key_exists('Head', $decoded[0]))) {
                return $decoded[0];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $record */
    private function recordIsEmpty(array $record): bool
    {
        $hasData = data_get($record, 'Head') || data_get($record, 'History') || data_get($record, 'Detail');
        $hasErrors = ! empty((array) data_get($record, 'Errors', []));

        return ! $hasData && $hasErrors;
    }

    private function noRecords(string $html): bool
    {
        return stripos($html, 'No records found') !== false;
    }

    /**
     * Best-effort parse of the website's embedded record. Field names under
     * Head/History aren't publicly documented, so we look through likely shapes
     * and derive status from human-readable text. Returns null if nothing usable
     * is found (caller then declines to guess).
     *
     * @param  array<string, mixed>  $record
     */
    private function fromWebsiteRecord(array $record): ?TrackingResult
    {
        $rawHistory = data_get($record, 'History')
            ?? data_get($record, 'Detail.History')
            ?? data_get($record, 'Head.History')
            ?? [];
        $rawHistory = is_array($rawHistory) ? $rawHistory : [];

        $events = [];
        foreach ($rawHistory as $h) {
            if (! is_array($h)) {
                continue;
            }
            $desc = (string) ($this->firstString($h, ['Description', 'StatusMessage', 'Status', 'Activity', 'Event', 'Message']) ?? '');
            if ($desc === '') {
                continue;
            }
            $when = $this->dateTime(
                $this->firstString($h, ['Date', 'DateTime', 'ActivityDate', 'EventDate', 'StatusDate']),
                $this->firstString($h, ['Time', 'ActivityTime', 'EventTime', 'StatusTime']),
            );
            $events[] = new TrackingEvent(
                $this->codeFor($desc),
                $desc,
                $this->location(
                    $this->firstString($h, ['City', 'ActivityCity', 'EventCity']),
                    $this->firstString($h, ['State', 'ActivityState', 'EventState']),
                ),
                $when ?? Carbon::now(),
            );
        }
        usort($events, fn ($a, $b) => $b->occurredAt <=> $a->occurredAt);

        $statusText = (string) ($this->firstString((array) data_get($record, 'Head', []), ['Status', 'StatusMessage', 'LongStatus', 'ShortStatus', 'CurrentStatus'])
            ?? ($events[0]->description ?? ''));

        // Without a status string and without any events, we have nothing to go on.
        if ($statusText === '' && $events === []) {
            return null;
        }

        $status = $this->resolveStatus($statusText, $events);

        $scheduled = $this->date($this->firstString((array) data_get($record, 'Head', []), ['EstimatedDelivery', 'ScheduledDelivery', 'DeliveryDate', 'ExpectedDelivery']));
        $deliveredAt = $status === MailingStatus::Delivered
            ? ($events[0]->occurredAt ?? Carbon::now())
            : null;

        return new TrackingResult(
            $status,
            null,
            $statusText ?: null,
            $scheduled,
            $deliveredAt,
            null,
            null,
            $events,
        );
    }

    // ── Shared helpers ──────────────────────────────────────────────────────

    private function pending(string $message): TrackingResult
    {
        return new TrackingResult(MailingStatus::LabelCreated, null, $message, null, null, null, null, []);
    }

    /** Map a freight milestone description to the carrier-agnostic event code. */
    private function codeFor(string $description): ?string
    {
        $d = strtolower($description);

        return match (true) {
            str_contains($d, 'delivered') => 'D',
            str_contains($d, 'out for delivery') => 'O',
            str_contains($d, 'exception') || str_contains($d, 'appointment') || str_contains($d, 'refused') || str_contains($d, 'hold') => 'X',
            str_contains($d, 'bill of lading') || str_contains($d, 'bol') || str_contains($d, 'booked') || str_contains($d, 'order received') || str_contains($d, 'manifest') => 'M',
            default => 'I',
        };
    }

    /**
     * Derive our status from R+L's text. Delivered/returned are terminal — if any
     * scan or the headline status says so, it wins. Only ever returns Delivered
     * when the text literally says "delivered" (never a fabricated default).
     *
     * @param  TrackingEvent[]  $events
     */
    private function resolveStatus(string $statusText, array $events): MailingStatus
    {
        $haystacks = array_map(
            fn (string $s) => strtolower($s),
            array_merge([$statusText], array_map(fn (TrackingEvent $e) => $e->description, $events)),
        );

        foreach ($haystacks as $h) {
            if (str_contains($h, 'delivered')) {
                return MailingStatus::Delivered;
            }
            if (str_contains($h, 'returned') || str_contains($h, 'refused')) {
                return MailingStatus::Returned;
            }
        }

        $latest = strtolower($statusText !== '' ? $statusText : ($events[0]->description ?? ''));

        return match (true) {
            str_contains($latest, 'out for delivery') => MailingStatus::OutForDelivery,
            str_contains($latest, 'exception') || str_contains($latest, 'appointment') || str_contains($latest, 'hold') || str_contains($latest, 'delay') => MailingStatus::Exception,
            str_contains($latest, 'bill of lading') || str_contains($latest, 'bol') || str_contains($latest, 'booked') || str_contains($latest, 'order received') || str_contains($latest, 'pending pickup') || str_contains($latest, 'manifest') => MailingStatus::LabelCreated,
            str_contains($latest, 'picked up') || str_contains($latest, 'in transit') || str_contains($latest, 'departed') || str_contains($latest, 'arrived') || str_contains($latest, 'en route') || str_contains($latest, 'out for') => MailingStatus::InTransit,
            default => MailingStatus::InTransit,
        };
    }

    private function location(mixed $city, mixed $state): ?string
    {
        $parts = array_filter([
            is_string($city) ? trim($city) : null,
            is_string($state) ? trim($state) : null,
        ], fn ($v) => $v !== null && $v !== '');

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string[]  $keys
     */
    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $v = data_get($row, $key);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_numeric($v)) {
                return (string) $v;
            }
        }

        return null;
    }

    private function dateTime(mixed $date, mixed $time = null): ?Carbon
    {
        $date = is_string($date) ? trim($date) : '';
        if ($date === '') {
            return null;
        }
        $time = is_string($time) ? trim($time) : '';

        try {
            return Carbon::parse(trim($date.' '.$time));
        } catch (Throwable) {
            return null;
        }
    }

    private function date(mixed $value): ?Carbon
    {
        $dt = $this->dateTime($value);

        return $dt?->startOfDay();
    }
}
