<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PostcodeService
{
    public function getDetails(string $postcode): ?array
    {
        $cleanPostcode = strtoupper(str_replace(' ', '', $postcode));
        
        $response = Http::get("https://api.postcodes.io/postcodes/" . urlencode($cleanPostcode));

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