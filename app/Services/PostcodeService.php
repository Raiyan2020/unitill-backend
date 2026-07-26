<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PostcodeService
{
    public function getDetails(string $postcode): ?array
    {
        $cleanPostcode = strtoupper(str_replace(' ', '', $postcode));

        // Without an explicit timeout this inherits PHP's default socket
        // timeout, so a slow provider holds a worker for far longer than the
        // request deserves. Retry once for a transient blip, then give up.
        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->retry(2, 200, throw: false)
            ->get('https://api.postcodes.io/postcodes/'.urlencode($cleanPostcode));

        if ($response->successful() && $response->json('status') === 200) {
            $data = $response->json('result');
            
            return [
                'postcode' => $data['postcode'],
                'region'    => $data['region'],
                'latitude' => $data['latitude'],
                'longitude'=> $data['longitude'],
                'city'     => $data['admin_district'],
            ];
        }

        return null;
    }
}