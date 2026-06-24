<?php

namespace App\Modules\Inventory\Services;

/**
 * Derives a product category from its name using a weighted keyword taxonomy.
 * Deterministic and provider-agnostic (no AI dependency) so it works offline and
 * is unit-testable. Multi-word phrases score higher than single words, and the
 * taxonomy is ordered by priority so ties resolve to the earlier (more specific)
 * family — e.g. a "Sensor Cable" lands in Cables, not Sensors.
 */
class ProductCategorizer
{
    /**
     * category => list of keywords. Phrases (with spaces) are matched as adjacent
     * words and weighted 3; single tokens are matched on word boundaries, weight 2.
     * Order matters: it breaks score ties in favour of the first-listed category.
     *
     * @var array<string, array<int, string>>
     */
    private const TAXONOMY = [
        // Services first — a name like "On-Site Support for X Sensor" is a service.
        'Services' => [
            'on-site', 'on site', 'onsite', 'remote support', 'technical support',
            'turnkey', 'site support', 'installation', 'commissioning', 'training',
            'maintenance', 'calibration', 'consultancy', 'consulting', 'support',
            'warranty', 'danismanlik', 'hizmeti', 'service', 'rental', 'subscription',
            'reporting', 'engineering service', 'field service', 'repair',
        ],
        'Software & Licensing' => [
            'software', 'license', 'licence', 'licensing', 'firmware', 'sdk',
            'software platform', 'processing software', 'application software',
            'algorithm', 'back-end', 'back end', 'e-learning', 'elearning',
        ],
        'Shake Tables & Structural Testing' => [
            'shake table', 'motion platform', 'motion simulator', 'loading frame',
            'load frame', 'test frame', 'reaction wall', '6dof', '6 dof', 'biaxial',
            'uniaxial', 'triaxial', 'actuator', 'loadcell', 'load cell', 'specimen',
            'safety railing', 'training simulator', 'trainingsimulator', 'simulator',
            'universal testing', 'testing machine', 'test system', 'testing system',
            'tensile', 'compression machine', 'electromechanical', 'shake', 'seismic table',
        ],
        'Monitoring & Early Warning' => [
            'early warning', 'structural health', 'health monitoring',
            'seismic monitoring', 'monitoring system', 'monitoring network',
        ],
        'Sensors' => [
            'seismograph', 'seismometer', 'accelerometer', 'accelerograph',
            'geophone', 'seismic switch', 'infrasound', 'acoustic emission',
            'velocimeter', 'hydrophone', 'tiltmeter', 'inclinometer', 'strainmeter',
            'strong motion', 'mems', 'rain sensor', 'sensor', 'transducer', 'detector',
        ],
        'Data Acquisition & Computing' => [
            'data acquisition', 'data processing', 'data harvesting', 'data logger',
            'datalogger', 'receiving and processing', 'processing system',
            'acquisition system', 'workstation', 'digitizer', 'recorder', 'server',
            'controller', 'gateway', 'acquisition', 'daq', 'computer', 'nas', 'cpu',
        ],
        'Antennas & Communications' => [
            'gps antenna', 'gnss antenna', 'cellular antenna', 'antenna', 'gnss',
            'gps', 'router', 'modem', 'cellular', 'lte', '4g', '5g', 'ethernet',
            'wifi', 'wireless', 'radio', 'telemetry', 'network',
        ],
        'Power & Energy' => [
            'solar power', 'power supply', 'power system', 'solar panel', 'solar',
            'battery', 'ups', 'charger', 'charging', 'inverter', 'generator',
            'adapter', 'voltage', 'vdc', 'vac', 'power', 'panel',
        ],
        'Cables & Connectors' => [
            'sensor cable', 'patch cord', 'pigtail', 'termination', 'connector',
            'gland', 'harness', 'rj45', 'cable', 'wire', 'wiring',
        ],
        'Enclosures & Cases' => [
            'transport case', 'sensor enclosure', 'instrument enclosure',
            'waterproof enclosure', 'enclosure', 'cabinet', 'housing', 'rack',
            'ip67', 'ip68', 'waterproof', 'weatherproof', 'case', 'box',
        ],
        'Storage & Media' => [
            'sd card', 'media card', 'memory card', 'hard disk', 'flash drive',
            'ssd', 'hdd', 'storage',
        ],
        'Surveillance & Imaging' => [
            'surveillance', 'camera', 'cctv', 'webcam', 'imaging', 'thermal camera',
        ],
        'Laser & Fabrication' => [
            'fiber laser', 'laser cutting', 'laser cutter', 'cutting machine',
            'laser', 'cnc', 'plasma cutter', 'engraver',
        ],
        'Mounting & Hardware' => [
            'mounting bracket', 'installation post', 'mounting', 'bracket', 'tripod',
            'mast', 'pole', 'railing', 'post', 'clamp', 'hardware', 'bolt', 'screw',
            'button', 'switch', 'stand', 'fixing',
        ],
        'Shipping & Fees' => [
            'shipping', 'shipment', 'freight', 'insurance', 'customs', 'duty',
            'travel', 'lodging', 'mobilization', 'deposit', 'vat', 'tax',
            'processing fee', 'fee', 'documentation', 'logistics',
        ],
    ];

    /**
     * Best-matching category for a product name, or null when nothing scores.
     */
    public function categorize(?string $name): ?string
    {
        $hay = $this->normalise((string) $name);
        if ($hay === ' ') {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach (self::TAXONOMY as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($kw, ' ')) {
                    if (str_contains($hay, ' '.$kw.' ')) {
                        $score += 3;
                    }
                } elseif (str_contains($hay, ' '.$kw.' ') || str_contains($hay, ' '.$kw.'s ')) {
                    // single token, also accept its simple plural ("sensor" → "sensors")
                    $score += 2;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $category;
            }
        }

        return $best;
    }

    /** @return list<string> the category names this service can assign */
    public function categories(): array
    {
        return array_keys(self::TAXONOMY);
    }

    /** Lowercase, strip punctuation to single spaces, pad with spaces for boundary matching. */
    private function normalise(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';

        return ' '.trim(preg_replace('/\s+/', ' ', $s) ?? '').' ';
    }
}
