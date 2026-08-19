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

    /**
     * Multibyte-safe: substr() would take the first BYTE of a name, and half of
     * an Arabic character is invalid UTF-8 — which made json_encode() fail for
     * the whole conversations response, not just this field.
     */
    protected function initials(): string
    {
        $first = mb_strtoupper(mb_substr(trim((string) ($this->first_name ?: $this->name)), 0, 1));
        $last = mb_strtoupper(mb_substr(trim((string) $this->last_name), 0, 1));

        return $first.($last ?: '');
    }
}
