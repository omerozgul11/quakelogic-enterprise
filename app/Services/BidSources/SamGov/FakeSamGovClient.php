<?php

namespace App\Services\BidSources\SamGov;

use App\Services\BidSources\BidSourceResultDTO;
use Carbon\Carbon;

class FakeSamGovClient
{
    private array $fakeOpportunities;

    public function __construct()
    {
        $this->fakeOpportunities = $this->generateFakeData();
    }

    public function searchOpportunities(array $params = []): array
    {
        $opportunities = $this->fakeOpportunities;

        if (!empty($params['naicsCode'])) {
            $naics = (array) $params['naicsCode'];
            $opportunities = array_filter($opportunities, fn($o) => in_array($o->naicsCode, $naics));
        }

        if (!empty($params['keyword'])) {
            $keyword = strtolower($params['keyword']);
            $opportunities = array_filter($opportunities, fn($o) =>
                str_contains(strtolower($o->title), $keyword) ||
                str_contains(strtolower((string) $o->description), $keyword)
            );
        }

        $limit = $params['limit'] ?? 25;
        $offset = $params['offset'] ?? 0;

        return array_values(array_slice($opportunities, $offset, $limit));
    }

    /** Full-text search behaves like a title/description keyword search on fake data. */
    public function searchFullText(string $keyword, int $limit = 25): array
    {
        return $this->searchOpportunities(['keyword' => $keyword, 'limit' => $limit]);
    }

    /** Fake full-text award search: same demo data as searchAwards. */
    public function searchAwardsFullText(string $keyword, int $limit = 25): array
    {
        return array_slice($this->searchAwards(['keyword' => $keyword]), 0, $limit);
    }

    /** Fake data has no per-notice award lookup. */
    public function getAward(string $noticeId): ?array
    {
        return null;
    }

    /** Demo award data for the market-pricing view when no live API is configured. */
    public function searchAwards(array $params = []): array
    {
        $keyword = isset($params['keyword']) ? strtolower((string) $params['keyword']) : '';
        $awards = [];
        foreach ($this->fakeOpportunities as $i => $o) {
            $awards[] = [
                'title' => $o->title,
                'agency' => $o->agencyName,
                'naics' => $o->naicsCode,
                'amount' => round((float) ($o->estimatedValue ?? 0) * (0.8 + ($i % 5) * 0.1), 2),
                'awardee' => ['Acme Federal LLC', 'Vanguard Systems Inc.', 'Northstar Solutions', 'Pinnacle Group'][$i % 4],
                'award_date' => Carbon::now()->subDays(($i % 300) + 10)->format('Y-m-d'),
                'solicitation_number' => $o->solicitationNumber,
                'posted_date' => optional($o->postedDate)->format('Y-m-d'),
                'set_aside' => $o->setAsideType,
                'url' => $o->sourceUrl,
            ];
        }
        if ($keyword !== '') {
            $awards = array_filter($awards, fn ($a) => str_contains(strtolower((string) $a['title']), $keyword));
        }
        return array_values(array_slice($awards, 0, 40));
    }

    public function getOpportunity(string $noticeId, ?\DateTimeInterface $postedNear = null): ?BidSourceResultDTO
    {
        foreach ($this->fakeOpportunities as $opp) {
            if ($opp->externalId === $noticeId) {
                return $opp;
            }
        }
        return null;
    }

    private function generateFakeData(): array
    {
        $agencies = [
            ['Department of Defense', 'Defense Advanced Research Projects Agency'],
            ['Department of Homeland Security', 'Cybersecurity and Infrastructure Security Agency'],
            ['General Services Administration', 'Federal Acquisition Service'],
            ['Department of Energy', 'Office of Science'],
            ['Department of Transportation', 'Federal Aviation Administration'],
            ['Department of Health and Human Services', 'Centers for Disease Control and Prevention'],
            ['Department of Veterans Affairs', 'Veterans Health Administration'],
            ['Department of Justice', 'Federal Bureau of Investigation'],
        ];

        $setAsides = ['Total Small Business', 'HUBZone', 'SDVOSB', 'WOSB', '8(a)', 'Full and Open'];
        $types = ['Solicitation', 'Presolicitation', 'Sources Sought', 'Special Notice'];
        $naicsCodes = ['541511', '541512', '541513', '541519', '541330', '541611', '561210', '334111'];
        $pscCodes = ['D301', 'D310', 'D399', 'R425', 'R499', 'C219', 'H219'];

        $opportunities = [];
        for ($i = 1; $i <= 50; $i++) {
            [$agency, $subAgency] = $agencies[array_rand($agencies)];
            $postedDate = Carbon::now()->subDays(rand(1, 60));
            $dueDate = Carbon::now()->addDays(rand(5, 90));

            $opportunities[] = new BidSourceResultDTO(
                externalId: 'SAM-FAKE-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                source: 'sam_gov',
                title: $this->generateTitle($i),
                solicitationNumber: strtoupper(sprintf('%s-%d-%04d', chr(64 + rand(1, 26)) . chr(64 + rand(1, 26)), date('Y'), rand(1000, 9999))),
                agencyName: $agency,
                subAgencyName: $subAgency,
                naicsCode: $naicsCodes[array_rand($naicsCodes)],
                pscCode: $pscCodes[array_rand($pscCodes)],
                setAsideType: $setAsides[array_rand($setAsides)],
                contractType: $types[array_rand($types)],
                estimatedValue: rand(50, 5000) * 1000.0,
                description: "This is a federal procurement opportunity {$i} for technology and professional services. The government seeks qualified vendors to provide support services. This fake opportunity was generated for demonstration purposes only.",
                postedDate: $postedDate,
                dueDate: $dueDate,
                placeOfPerformanceCity: ['Washington', 'Arlington', 'Bethesda', 'Reston', 'McLean'][array_rand(['Washington', 'Arlington', 'Bethesda', 'Reston', 'McLean'])],
                placeOfPerformanceState: 'DC',
                placeOfPerformanceCountry: 'US',
                sourceUrl: "https://sam.gov/opp/fake-{$i}/view",
                rawData: ['notice_id' => 'SAM-FAKE-' . str_pad($i, 6, '0', STR_PAD_LEFT), 'type' => 'Solicitation'],
            );
        }

        return $opportunities;
    }

    private function generateTitle(int $i): string
    {
        $titles = [
            "IT Infrastructure Modernization Support Services",
            "Cybersecurity Assessment and Risk Management",
            "Cloud Migration and DevSecOps Support",
            "Data Analytics Platform Development",
            "Enterprise Software Development Services",
            "Program Management Support Services",
            "Artificial Intelligence and Machine Learning Solutions",
            "Network Operations and Maintenance",
            "Systems Integration and Testing Services",
            "Strategic Communications and IT Consulting",
        ];
        return $titles[($i - 1) % count($titles)] . " (Opportunity {$i})";
    }
}
