<?php

namespace Tests\Unit\Inventory;

use App\Modules\Inventory\Services\ProductCategorizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure (no-DB) checks of the name → category taxonomy. Locks in the precision of
 * the weighted keyword matcher, including the phrase-beats-word tie-breaking that
 * keeps "sensor cable" out of Sensors and "sensor enclosure" out of Sensors.
 */
class ProductCategorizerTest extends TestCase
{
    private ProductCategorizer $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ProductCategorizer;
    }

    #[DataProvider('cases')]
    public function test_categorizes_by_name(string $name, ?string $expected): void
    {
        $this->assertSame($expected, $this->svc->categorize($name));
    }

    public static function cases(): array
    {
        return [
            'seismograph is a sensor' => ['TRITON-C-BB-120S SEISMOGRAPH', 'Sensors'],
            'plural sensors still match' => ['seismic sensors (mitigator+ / ind)', 'Sensors'],
            'sensor cable is a cable, not a sensor' => ['SIS-1 REFTEK SENSOR CABLE', 'Cables & Connectors'],
            'sensor enclosure is an enclosure' => ['STAINLESS STEEL SENSOR ENCLOSURE FOR QUAKELY', 'Enclosures & Cases'],
            'plural enclosures match' => ['ss300 ground-mounted enclosures', 'Enclosures & Cases'],
            'shake table' => ['BIAXIAL SHAKE TABLE', 'Shake Tables & Structural Testing'],
            'motion platform is structural, not software' => ['SANLAB TRUE 6DOF ELECTRIC MOTION PLATFORM', 'Shake Tables & Structural Testing'],
            'solar power system is power' => ['200 W SOLAR POWER SYSTEM', 'Power & Energy'],
            'battery is power' => ['12 V BATTERY - 6 AH', 'Power & Energy'],
            'gnss antenna beats mounting hardware' => ['GNSS/GPS ANTENNA WITH MOUNTING BRACKET AND HARDWARE', 'Antennas & Communications'],
            'calibration is a service' => ['ANNUAL CALIBRATION', 'Services'],
            'on-site support is a service even with hardware words' => ['On-Site Technical Support – SHAKE SS300 Smart Seismic Switch', 'Services'],
            'software wins over hardware tokens' => ['LOADCELL SOFTWARE INTEGRATION', 'Software & Licensing'],
            'ssd is storage' => ['EXTERNAL SSD HARD DISK', 'Storage & Media'],
            'camera is surveillance' => ['Surveillance Camera', 'Surveillance & Imaging'],
            'data harvesting is computing' => ['DATA HARVESTING SYSTEM', 'Data Acquisition & Computing'],
            'early warning system' => ['Earthquake Early Warning System', 'Monitoring & Early Warning'],
            'structural health monitoring' => ['Structural Health Monitoring System - Silos 6 & 7', 'Monitoring & Early Warning'],
            'fiber laser is fabrication' => ['QL-FusionCore 1200 – All-in-One Enclosed Fiber Laser System', 'Laser & Fabrication'],
            'shipping is a fee line' => ['SHIPPING & INSURANCE', 'Shipping & Fees'],
            'travel is a fee line' => ['TRAVEL, LODGING', 'Shipping & Fees'],
            'model-number only stays uncategorized' => ['TRITON-FB160-HHV', null],
            'empty name is null' => ['', null],
        ];
    }
}
