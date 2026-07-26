<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vehicle lookup via Vehicle Data Global (vehicledataglobal.com).
 *
 * Trial/sandbox accounts only resolve registrations containing the letter "A";
 * anything else comes back as NoResultsFound rather than as an error.
 */

//AB12CDE، KM19ATT،AD58VNK     Test Plates
class VehicleApiService
{
    protected string $apiUrl = 'https://uk.api.vehicledataglobal.com/r2/lookup';

    protected string $package = 'VehicleDetails';

    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.vehicle_api.key');
    }

    /**
     * Maps the provider payload onto the Cars category attribute slugs.
     * Missing values are omitted rather than defaulted, so the client can
     * leave those fields empty instead of pre-filling a wrong guess.
     */
    public function getMappedVehicleData(string $vrm): array
    {
        $data = $this->getVehicleData($vrm);

        if (isset($data['error'])) {
            return $data;
        }

        $results = $data['Results'] ?? [];
        $identification = data_get($results, 'VehicleDetails.VehicleIdentification', []);
        $model = data_get($results, 'ModelDetails.ModelIdentification', []);
        $body = data_get($results, 'ModelDetails.BodyDetails', []);
        $powertrain = data_get($results, 'ModelDetails.Powertrain', []);
        $technical = data_get($results, 'VehicleDetails.DvlaTechnicalDetails', []);

        $mapped = [
            'make' => $model['Make'] ?? $this->titleCase($identification['DvlaMake'] ?? null),
            'model' => $model['Model'] ?? $model['Range'] ?? $this->titleCase($identification['DvlaModel'] ?? null),
            'year' => $identification['YearOfManufacture'] ?? null,
            'fuel_type' => $this->mapFuelType($powertrain['FuelType'] ?? $identification['DvlaFuelType'] ?? null),
            'transmission' => $this->mapTransmission(data_get($powertrain, 'Transmission.TransmissionType')),
            'body_type' => $this->mapBodyType($body['BodyStyle'] ?? null),
            'colour' => $this->titleCase(data_get($results, 'VehicleDetails.VehicleHistory.ColourDetails.CurrentColour')),
            'engine_size' => $technical['EngineCapacityCc'] ?? data_get($powertrain, 'IceDetails.EngineCapacityCc'),
            'seats' => $this->mapSeats($technical['NumberOfSeats'] ?? $body['NumberOfSeats'] ?? null),
        ];

        return array_map(
            fn ($value) => $value === null ? null : (string) $value,
            array_filter($mapped, fn ($value) => $value !== null && $value !== '')
        );
    }

    public function getVehicleData(string $vrm): array
    {
        $vrm = strtoupper(preg_replace('/\s+/', '', $vrm));

        if (empty($this->apiKey)) {
            Log::error('Vehicle lookup attempted without VEHICLE_API_KEY configured');

            return ['error' => 'not_configured'];
        }

        $cacheKey = "vehicle_{$this->package}_{$vrm}";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)
                ->retry(2, 200, throw: false)
                // The provider sits behind a WAF that rejects the default Guzzle
                // agent, so an explicit User-Agent is required.
                ->withHeaders(['User-Agent' => 'UniTill/1.0'])
                ->get($this->apiUrl, [
                    'packagename' => $this->package,
                    'apikey' => $this->apiKey,
                    'vrm' => $vrm,
                ]);
        } catch (\Throwable $e) {
            Log::error('Vehicle lookup request failed', ['vrm' => $vrm, 'message' => $e->getMessage()]);

            return ['error' => 'unavailable'];
        }

        if (! $response->successful()) {
            Log::error('Vehicle lookup returned a non-200 response', [
                'vrm' => $vrm,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return ['error' => 'unavailable'];
        }

        $payload = $response->json();

        // The provider answers 200 even for lookup failures; the real outcome
        // is in ResponseInformation.
        if (! data_get($payload, 'ResponseInformation.IsSuccessStatusCode', false)) {
            $status = data_get($payload, 'ResponseInformation.StatusMessage', 'Unknown');

            // NoResultsFound is a legitimate answer, not a fault worth logging.
            if ($status !== 'NoResultsFound') {
                Log::warning('Vehicle lookup unsuccessful', ['vrm' => $vrm, 'status' => $status]);
            }

            return ['error' => 'not_found'];
        }

        // Only successful lookups are cached — caching failures would lock a
        // registration out for a full day after one transient outage.
        Cache::put($cacheKey, $payload, now()->addHours(24));

        return $payload;
    }

    private function mapFuelType(?string $value): ?string
    {
        return $this->mapToOption($value, ['Petrol', 'Diesel', 'Hybrid', 'Electric'], 'Other');
    }

    private function mapTransmission(?string $value): ?string
    {
        return $this->mapToOption($value, ['Manual', 'Automatic'], 'Other');
    }

    private function mapBodyType(?string $value): ?string
    {
        return $this->mapToOption(
            $value,
            ['Hatchback', 'Saloon', 'SUV', 'Estate', 'Coupe', 'Convertible', 'Pickup'],
            'Other'
        );
    }

    /**
     * The seats attribute is a select of 2/4/5/7+, so anything above 5 collapses.
     */
    private function mapSeats(int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $seats = (int) $value;

        return match (true) {
            $seats >= 7 => '7+',
            in_array($seats, [2, 4, 5], true) => (string) $seats,
            default => null,
        };
    }

    /**
     * Matches a provider value against our attribute options, case-insensitively.
     * Returns null for empty input so the field is left blank rather than
     * defaulted to "Other".
     */
    private function mapToOption(?string $value, array $options, string $fallback): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach ($options as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        return $fallback;
    }

    private function titleCase(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : ucwords(strtolower($value));
    }
}
