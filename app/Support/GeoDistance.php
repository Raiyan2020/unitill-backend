<?php

namespace App\Support;

class GeoDistance
{
    /**
     * Great-circle (haversine) distance in km as a raw SQL expression against
     * the ads table's `latitude`/`longitude` columns. Bind three params in
     * order: [lat, lng, lat]. LEAST() guards against floating-point rounding
     * pushing the acos() argument above 1. Shared by distance sorting (ORDER BY)
     * and the radius filter (WHERE).
     */
    public static function sqlKm(): string
    {
        return '(6371 * acos(LEAST(1, cos(radians(?)) * cos(radians(latitude)) '
            .'* cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))';
    }
}
