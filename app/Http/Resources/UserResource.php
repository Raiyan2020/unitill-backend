<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = $request->header('lang') == 'en' ? 'name_en' : 'name_ar';
        $mask = function (?string $e) {
            if (! $e || ! str_contains($e, '@')) {
                return null;
            }
            [$local, $domain] = explode('@', $e, 2);

            return (strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2)).'***@'.$domain;
        };

        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'student_email_masked' => $mask($this->student_email),
            'is_trusted_seller' => (bool) $this->is_trusted_seller,
            'device_type' => $this->device_type,
            'city_id' => (int) $this->city_id,
            'city_name' => $this->city ? $this->city->$name : null,
            'activation_code' => $this->activation_code,
            'status' => ($this->status === '1' || $this->status === 1)
                ? 'active'
                : (($this->status === '2' || $this->status === 2) ? 'pending_verification' : 'inactive'),
        ];
    }
}
