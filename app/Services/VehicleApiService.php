<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class VehicleApiService
{
    protected $apiUrl = 'https://api.vehiclesmart.com/v1/vehicles';
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.vehicle_api.key');
    }

    public function getMappedVehicleData(string $vrm)
    {
        $data = $this->getVehicleData($vrm);

        if (isset($data['error'])) {
            return $data;
        }

        return [
            'make' => $data['make'] ?? 'Other',
            'model' => $data['model'] ?? 'Unknown',
            'year' => (string)($data['yearOfManufacture'] ?? ''),
            'fuel_type' => $data['fuelType'] ?? 'Other',
            'transmission' => $data['transmission'] ?? 'Other',
            'body_type' => $data['bodyType'] ?? 'Other',
            'colour' => $data['colour'] ?? 'Other',
        ];
    }

    public function getVehicleData(string $vrm)
    {
        $vrm = strtoupper(str_replace(' ', '', $vrm));

        return Cache::remember("vehicle_{$vrm}", now()->addHours(24), function () use ($vrm) {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->post($this->apiUrl, ['registrationNumber' => $vrm]);

            return $response->successful() ? $response->json() : ['error' => 'Not found'];
        });
    }
}