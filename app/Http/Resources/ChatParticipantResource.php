<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'initials' => $this->initials(),
            'image' => $this->image ? getimg($this->image) : null,
            'is_online' => false,
        ];
    }

    protected function displayName(): string
    {
        $firstName = trim((string) ($this->first_name ?: explode(' ', (string) $this->name)[0] ?? ''));
        $lastName = trim((string) $this->last_name);

        return trim($firstName.' '.$lastName) ?: (string) $this->name;
    }

    protected function initials(): string
    {
        $first = strtoupper(substr(trim((string) ($this->first_name ?: $this->name)), 0, 1));
        $last = strtoupper(substr(trim((string) $this->last_name), 0, 1));

        return $first.($last ?: '');
    }
}
