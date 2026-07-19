<?php

namespace App\Http\Resources\Concerns;

/**
 * Trait ApproximatesLocation
 * 
 * Provides location privacy by masking exact user addresses.
 * Since ads are posted by students, full postcodes and exact coordinates 
 * could expose their home address. This trait is shared across resources 
 * to ensure consistent privacy masking for both lists and detail views.
 */
trait ApproximatesLocation
{
    /**
     * Extracts the "outward" part of a UK postcode (e.g., "LS2 9JT" becomes "LS2").
     * This restricts location data to a general area (neighborhood level) 
     * rather than a specific building.
     */
    protected function outwardPostcode(): ?string
    {
        $postcode = trim((string) $this->postcode);
        if ($postcode === '') {
            return null;
        }

        // Remove spaces and normalize to uppercase
        $normalized = strtoupper(preg_replace('/\s+/', '', $postcode));

        // UK postcodes end with a 3-character "inward" code; we strip it
        return strlen($normalized) > 3
            ? substr($normalized, 0, -3)
            : $normalized;
    }

    /**
     * Rounds coordinates to 2 decimal places.
     * 
     * This provides a precision of approximately 1.1km. 
     * Note: This does not affect distance sorting, as database 
     * calculations use the original precise values.
     */
    protected function approximateCoordinate(float|string|null $coordinate): ?float
    {
        return $coordinate === null ? null : round((float) $coordinate, 2);
    }

    /**
     * Helper to determine if the authenticated user is the ad owner.
     */
    protected function viewerOwnsAd(): bool
    {
        $viewerId = auth('sanctum')->id();

        return $viewerId !== null && (int) $this->user_id === (int) $viewerId;
    }

    /**
     * Generates the location data payload based on viewer permissions.
     * 
     * If the viewer is the owner, they see the precise data.
     * Otherwise, they receive masked/approximated values.
     */
    protected function locationPayload(): array
    {
        $isOwner = $this->viewerOwnsAd();

        return [
            'postcode' => $isOwner ? $this->postcode : $this->outwardPostcode(),
            'latitude' => $isOwner
                ? $this->latitude
                : $this->approximateCoordinate($this->latitude),
            'longitude' => $isOwner
                ? $this->longitude
                : $this->approximateCoordinate($this->longitude),
            // Flag for the frontend to know if it should display a "General Area" warning
            'is_approximate_location' => ! $isOwner,
        ];
    }
}