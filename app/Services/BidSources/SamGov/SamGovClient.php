<?php

namespace App\Services\BidSources\SamGov;

use App\Services\BidSources\BidSourceResultDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SamGovClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.sam.gov/opportunities/v2',
    ) {}

    /**
     * @return BidSourceResultDTO[]
     */
    public function searchOpportunities(array $params = []): array
    {
        // Accept both the client-native keys (ncode/title) and the controller's
        // filter keys (naics_codes[]/keywords).
        $naics = $params['naicsCode'] ?? $params['ncode'] ?? null;
        if (!$naics && !empty($params['naics_codes'])) {
            $naics = is_array($params['naics_codes']) ? ($params['naics_codes'][0] ?? null) : $params['naics_codes'];
        }
        $title = $params['keyword'] ?? $params['title'] ?? $params['keywords'] ?? null;

        $query = array_filter([
            'api_key' => $this->apiKey,
            'limit' => $params['limit'] ?? 50,
            'offset' => $params['offset'] ?? 0,
            'postedFrom' => $params['postedFrom'] ?? now()->subDays(30)->format('m/d/Y'),
            'postedTo' => $params['postedTo'] ?? now()->format('m/d/Y'),
            'ncode' => $naics,
            'title' => $title,
            'ptype' => $params['ptype'] ?? null,
            'state' => $params['state'] ?? null,
            // Response-deadline window: lets callers request only still-open notices.
            'rdlfrom' => $params['rdlfrom'] ?? null,
            'rdlto' => $params['rdlto'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $response = Http::timeout(30)->retry(2, 300)
            ->get(rtrim($this->baseUrl, '/') . '/search', $query);

        if (!$response->successful()) {
            Log::warning('SAM.gov API request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 300),
            ]);
            return [];
        }

        return array_map(fn ($item) => $this->mapToDto($item), $response->json('opportunitiesData', []) ?? []);
    }

    /**
     * Full-text keyword search via SAM.gov's UI search endpoint. The official
     * Get Opportunities API only matches the keyword against notice TITLES;
     * this endpoint matches descriptions too — the same behavior users get on
     * sam.gov itself. It's an unofficial endpoint, so any failure or response
     * shape change degrades to an empty result.
     *
     * Only still-open, non-award notices are returned: closed ones would be
     * purged from the pipeline immediately after import anyway.
     *
     * @return BidSourceResultDTO[]
     */
    public function searchFullText(string $keyword, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->get('https://sam.gov/api/prod/sgs/v1/search/', [
                'index' => 'opp',
                'q' => $keyword,
                'page' => 0,
                'size' => $limit,
                'sort' => '-modifiedDate',
                'is_active' => 'true',
                'mode' => 'search',
            ]);

            if (!$response->successful()) {
                Log::warning('SAM.gov full-text search failed', ['status' => $response->status()]);
                return [];
            }

            $hits = $response->json('_embedded.results', []) ?? [];
        } catch (\Throwable $e) {
            Log::warning('SAM.gov full-text search error', ['error' => $e->getMessage()]);
            return [];
        }

        $today = Carbon::now()->startOfDay();
        $results = [];

        foreach ($hits as $hit) {
            $id = (string) ($hit['_id'] ?? '');
            $type = $hit['type']['value'] ?? '';
            if ($id === '' || stripos($type, 'award') !== false) {
                continue;
            }

            $dueDate = $this->parseDate($hit['responseDate'] ?? null);
            if ($dueDate && $dueDate < $today) {
                continue;
            }

            $orgLevels = collect($hit['organizationHierarchy'] ?? [])->sortBy('level')->values();
            $naics = $hit['naicsCodes'] ?? null;
            $naicsCode = is_array($naics)
                ? (is_array($naics[0] ?? null) ? ($naics[0]['naicsCode'] ?? $naics[0]['code'] ?? null) : ($naics[0] ?? null))
                : null;
            $description = $hit['descriptions'][0]['content'] ?? null;

            $results[] = new BidSourceResultDTO(
                externalId: $id,
                source: 'sam_gov',
                title: $hit['title'] ?? 'Untitled',
                solicitationNumber: $hit['solicitationNumber'] ?? null,
                agencyName: $orgLevels->first()['name'] ?? null,
                subAgencyName: $orgLevels->skip(1)->first()['name'] ?? null,
                naicsCode: is_string($naicsCode) ? $naicsCode : null,
                pscCode: null,
                setAsideType: null,
                contractType: $type ?: null,
                estimatedValue: null,
                description: is_string($description) ? trim(strip_tags($description)) : null,
                postedDate: $this->parseDate($hit['publishDate'] ?? null),
                dueDate: $dueDate,
                placeOfPerformanceCity: null,
                placeOfPerformanceState: null,
                placeOfPerformanceCountry: null,
                sourceUrl: 'https://sam.gov/opp/' . $id . '/view',
                rawData: $hit,
            );
        }

        return $results;
    }

    /**
     * Search past awarded contracts (SAM "Award Notice" type) for pricing
     * benchmarks. Returns a normalized array including the award amount and
     * awardee, which the opportunity DTO doesn't carry.
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchAwards(array $params = []): array
    {
        // SAM caps the posted-date window at under one year, so default to ~11 months.
        $query = array_filter([
            'api_key' => $this->apiKey,
            'limit' => $params['limit'] ?? 100,
            'offset' => $params['offset'] ?? 0,
            'ptype' => 'a', // Award Notice
            'postedFrom' => $params['postedFrom'] ?? now()->subMonths(11)->format('m/d/Y'),
            'postedTo' => $params['postedTo'] ?? now()->format('m/d/Y'),
            'ncode' => $params['naicsCode'] ?? null,
            'title' => $params['keyword'] ?? null,
            'state' => $params['state'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // No retry-throw here: a 4xx (e.g. bad filter) should degrade to an empty
        // result, not bubble an exception up to the page.
        $response = Http::timeout(30)->get(rtrim($this->baseUrl, '/') . '/search', $query);

        if (!$response->successful()) {
            Log::warning('SAM.gov awards request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 300),
            ]);
            return [];
        }

        return array_map(fn ($i) => $this->mapAward($i), $response->json('opportunitiesData', []) ?? []);
    }

    /**
     * Full-text search for AWARD notices via SAM.gov's UI search endpoint —
     * the official API only matches the keyword against titles, which misses
     * almost all niche-keyword awards. These hits never carry the award amount
     * (only the official API exposes it), so callers enrich via getAward().
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchAwardsFullText(string $keyword, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->get('https://sam.gov/api/prod/sgs/v1/search/', [
                'index' => 'opp',
                'q' => $keyword,
                'page' => 0,
                'size' => $limit,
                'sort' => '-modifiedDate',
                'mode' => 'search',
                'notice_type' => 'a',
            ]);

            if (!$response->successful()) {
                Log::warning('SAM.gov full-text award search failed', ['status' => $response->status()]);
                return [];
            }

            $hits = $response->json('_embedded.results', []) ?? [];
        } catch (\Throwable $e) {
            Log::warning('SAM.gov full-text award search error', ['error' => $e->getMessage()]);
            return [];
        }

        $awards = [];

        foreach ($hits as $hit) {
            $id = (string) ($hit['_id'] ?? '');
            if ($id === '' || ($hit['isCanceled'] ?? false)) {
                continue;
            }

            $orgLevels = collect($hit['organizationHierarchy'] ?? [])->sortBy('level')->values();
            $naics = $hit['naicsCodes'] ?? null;
            $naicsCode = is_array($naics)
                ? (is_array($naics[0] ?? null) ? ($naics[0]['naicsCode'] ?? $naics[0]['code'] ?? null) : ($naics[0] ?? null))
                : null;
            $amount = $hit['award']['amount'] ?? null;

            $awards[] = [
                'external_id' => $id,
                'title' => $hit['title'] ?? 'Untitled',
                'agency' => $orgLevels->first()['name'] ?? null,
                'naics' => is_string($naicsCode) ? $naicsCode : null,
                'amount' => is_numeric($amount) ? (float) $amount : null,
                'awardee' => $hit['award']['awardee']['name'] ?? null,
                'award_date' => $hit['award']['date'] ?? null,
                'solicitation_number' => $hit['solicitationNumber'] ?? null,
                'posted_date' => isset($hit['publishDate']) ? substr((string) $hit['publishDate'], 0, 10) : null,
                'set_aside' => null,
                'url' => 'https://sam.gov/opp/' . $id . '/view',
            ];
        }

        return $awards;
    }

    /**
     * Award details (amount, awardee, award date) for a single notice via the
     * official API — the only place SAM exposes the award amount.
     *
     * @return array<string,mixed>|null
     */
    public function getAward(string $noticeId): ?array
    {
        $response = Http::timeout(30)->get(rtrim($this->baseUrl, '/') . '/search', [
            'api_key' => $this->apiKey,
            'noticeid' => $noticeId,
            'limit' => 1,
            'postedFrom' => now()->subMonths(11)->format('m/d/Y'),
            'postedTo' => now()->format('m/d/Y'),
        ]);

        if (!$response->successful()) {
            return null;
        }

        $items = $response->json('opportunitiesData', []) ?? [];

        return $items ? $this->mapAward($items[0]) : null;
    }

    /** @return array<string,mixed> */
    private function mapAward(array $i): array
    {
        $amount = $i['award']['amount'] ?? null;

        return [
            'title' => $i['title'] ?? 'Untitled',
            'agency' => $i['fullParentPathName'] ?? $i['organizationName'] ?? $i['departmentName'] ?? null,
            'naics' => $i['naicsCode'] ?? ($i['naicsCodes'][0] ?? null),
            'amount' => is_numeric($amount) ? (float) $amount : null,
            'awardee' => $i['award']['awardee']['name'] ?? null,
            'award_date' => $i['award']['date'] ?? null,
            'solicitation_number' => $i['solicitationNumber'] ?? null,
            'posted_date' => $i['postedDate'] ?? null,
            'set_aside' => $i['typeOfSetAsideDescription'] ?? null,
            'url' => $i['uiLink'] ?? null,
        ];
    }

    public function getOpportunity(string $noticeId, ?\DateTimeInterface $postedNear = null): ?BidSourceResultDTO
    {
        // SAM's v2 search requires postedFrom/postedTo and rejects a range a full
        // year (or more) apart — "Date range must be null year(s) apart". Use a
        // window just under a year, centered on the notice's posting date when we
        // know it, so notices older than a year still resolve.
        [$from, $to] = $this->noticeDateWindow($postedNear);

        $response = Http::timeout(30)->get(rtrim($this->baseUrl, '/') . '/search', [
            'api_key' => $this->apiKey,
            'noticeid' => $noticeId,
            'limit' => 1,
            'postedFrom' => $from,
            'postedTo' => $to,
        ]);

        // Throttling (429) or a SAM-side error (5xx) is transient — surface it as
        // an exception so the caller retries later instead of caching a bogus
        // "no documents" miss. A 4xx (e.g. bad/expired notice) is a real miss.
        if ($response->status() === 429 || $response->serverError()) {
            Log::warning('SAM.gov opportunity lookup throttled/unavailable', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200),
            ]);
            throw new SamThrottledException("SAM.gov lookup failed (HTTP {$response->status()})");
        }
        if (!$response->successful()) {
            return null;
        }

        $items = $response->json('opportunitiesData', []) ?? [];
        return $items ? $this->mapToDto($items[0]) : null;
    }

    /**
     * A valid SAM postedFrom/postedTo window (just under one year): centered on a
     * known posting date, else the trailing ~year up to today. m/d/Y strings.
     *
     * @return array{0:string,1:string}
     */
    private function noticeDateWindow(?\DateTimeInterface $postedNear): array
    {
        $today = Carbon::now();

        if ($postedNear) {
            $from = Carbon::parse($postedNear->format('Y-m-d'))->subDays(7);
            $to = $from->copy()->addDays(350);
            if ($to->greaterThan($today)) {
                $to = $today->copy();
            }
            if ($from->greaterThan($to)) {
                $from = $to->copy()->subDays(350);
            }

            return [$from->format('m/d/Y'), $to->format('m/d/Y')];
        }

        return [$today->copy()->subDays(364)->format('m/d/Y'), $today->format('m/d/Y')];
    }

    private function mapToDto(array $i): BidSourceResultDTO
    {
        $description = $i['description'] ?? null;
        if (!is_string($description) || str_starts_with($description, 'http')) {
            $description = null;
        }

        return new BidSourceResultDTO(
            externalId: (string) ($i['noticeId'] ?? $i['_id'] ?? ''),
            source: 'sam_gov',
            title: $i['title'] ?? 'Untitled',
            solicitationNumber: $i['solicitationNumber'] ?? null,
            agencyName: $i['fullParentPathName'] ?? $i['organizationName'] ?? $i['departmentName'] ?? null,
            subAgencyName: $i['subTier'] ?? null,
            naicsCode: $i['naicsCode'] ?? ($i['naicsCodes'][0] ?? null),
            pscCode: $i['classificationCode'] ?? null,
            setAsideType: $i['typeOfSetAsideDescription'] ?? $i['typeOfSetAside'] ?? null,
            contractType: $i['type'] ?? null,
            estimatedValue: isset($i['award']['amount']) && is_numeric($i['award']['amount']) ? (float) $i['award']['amount'] : null,
            description: $description,
            postedDate: $this->parseDate($i['postedDate'] ?? null),
            dueDate: $this->parseDate($i['responseDeadLine'] ?? null),
            placeOfPerformanceCity: $i['placeOfPerformance']['city']['name'] ?? null,
            placeOfPerformanceState: $i['placeOfPerformance']['state']['code'] ?? ($i['placeOfPerformance']['state']['name'] ?? null),
            placeOfPerformanceCountry: $i['placeOfPerformance']['country']['code'] ?? null,
            sourceUrl: $i['uiLink'] ?? null,
            rawData: $i,
        );
    }

    private function parseDate(?string $value): ?\DateTimeInterface
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
