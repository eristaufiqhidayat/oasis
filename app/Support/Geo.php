<?php

namespace App\Support;

class Geo
{
    /** Jarak dua titik dalam meter (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Apakah titik berada di dalam GeoJSON Polygon / MultiPolygon (koordinat [lng, lat]). Ray casting,
     * lubang polygon (ring ke-2 dst.) dihitung sebagai di luar.
     */
    public static function contains(?array $geometry, float $lat, float $lng): bool
    {
        if (! $geometry || ! isset($geometry['type'], $geometry['coordinates'])) {
            return false;
        }

        $polygons = match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };

        foreach ($polygons as $rings) {
            if (! self::inRing($rings[0] ?? [], $lat, $lng)) {
                continue;
            }

            $inHole = false;
            foreach (array_slice($rings, 1) as $hole) {
                $inHole = $inHole || self::inRing($hole, $lat, $lng);
            }

            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    private static function inRing(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $n = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
