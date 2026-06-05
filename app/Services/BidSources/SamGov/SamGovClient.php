<?php

namespace App\Services\BidSources\SamGov;

use App\Services\BidSources\BidSourceResultDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SamGovClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @return BidSourceResultDTO[]
     */
    public function fetchOpportunities(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->timeout(30)
            ->get("{$this->baseUrl}/search", array_merge($filters, [
                'limit' => $limit,
                'offset' => $offset,
                'postedFrom' => $filters['postedFrom'] ?? now()->subDays(30)->format('m/d/Y'),
                'postedTo' => $filters['postedTo'] ?? now()->format('m/d/Y'),
            ]));

        if (!$response->successful()) {
            Log::warning('SAM.gov API request failed', ['status' => $response->status()]);
            return [];
        }

        $data = $response->json();
        $opportunities = $data['opportunitiesData'] ?? [];

        return array_map(fn($item) => $this->mapToDto($item), $opportunities);
    }

    public function fetchOpportunity(string $noticeId): ?BidSourceResultDTO
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->timeout(30)
            ->get("{$this->baseUrl}/search", ['noticeid' => $noticeId]);

        if (!$response->successful()) {
            return null;
        }

        $items = $response->json('opportunitiesData', []);
        if (empty($items)) {
            return null;
        }

        return $this->mapToDto($items[0]);
    }

    private function mapToDto(array $item): BidSourceResultDTO
    {
        return new BidSourceResultDTO(
            externalId: $item['noticeId'] ?? '',
            solicitationNumber: $item['solicitationNumber'] ?? null,
            title: $item['title'] ?? 'Untitled',
            description: $item['description'] ?? null,
            agencyName: $item['fullParentPathName'] ?? $item['organizationName'] ?? null,
            agencyCode: $item['organizationId'] ?? null,
            naicsCode: $item['naicsCode'] ?? null,
            type: $item['type'] ?? null,
            setAside: $item['typeOfSetAsideDescription'] ?? null,
            placeOfPerformance: $item['placeOfPerformance']['state']['code'] ?? null,
            responseDeadline: isset($item['responseDeadLine']) ? date('Y-m-d', strtotime($item['responseDeadLine'])) : null,
            archiveDate: isset($item['archiveDate']) ? date('Y-m-d', strtotime($item['archiveDate'])) : null,
            estimatedValue: null,
            postedDate: isset($item['postedDate']) ? date('Y-m-d', strtotime($item['postedDate'])) : null,
            sourceUrl: $item['uiLink'] ?? null,
            rawData: $item,
        );
    }
}
