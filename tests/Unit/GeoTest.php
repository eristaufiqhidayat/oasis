<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    private array $square = ['type' => 'Polygon', 'coordinates' => [[[107.0, -7.0], [108.0, -7.0], [108.0, -6.0], [107.0, -6.0], [107.0, -7.0]]]];

    public function test_titik_di_dalam_dan_di_luar_polygon(): void
    {
        $this->assertTrue(Geo::contains($this->square, -6.5, 107.5));
        $this->assertFalse(Geo::contains($this->square, -5.5, 107.5));
        $this->assertFalse(Geo::contains(null, -6.5, 107.5));
    }

    public function test_lubang_polygon_dianggap_di_luar(): void
    {
        $withHole = $this->square;
        $withHole['coordinates'][] = [[107.4, -6.6], [107.6, -6.6], [107.6, -6.4], [107.4, -6.4], [107.4, -6.6]];

        $this->assertFalse(Geo::contains($withHole, -6.5, 107.5));
        $this->assertTrue(Geo::contains($withHole, -6.9, 107.1));
    }

    public function test_jarak_haversine(): void
    {
        // 0,001 derajat lintang ≈ 111 m.
        $this->assertEqualsWithDelta(111, Geo::distance(-6.9, 107.6, -6.901, 107.6), 2);
    }
}
