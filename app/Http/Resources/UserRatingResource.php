<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $request->header('lang', 'en');
        $locale = $lang === 'ar' ? 'ar' : 'en';

        return [
            'id' => $this->id,
            'score' => (int) $this->score,
            'comment' => $this->comment,
            'reviewer_name' => $this->reviewerDisplayName($this->rater),
            'reviewer_initials' => $this->reviewerInitials($this->rater),
            'date' => $this->created_at
                ? strtoupper($this->created_at->locale($locale)->translatedFormat('M d, Y'))
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected function reviewerDisplayName(?User $rater): string
    {
        if (! $rater) {
            return 'Anonymous';
        }

        $firstName = trim((string) ($rater->first_name ?: explode(' ', (string) $rater->name)[0] ?? ''));
        $lastInitial = $rater->last_name
            ? strtoupper(substr(trim($rater->last_name), 0, 1)).'.'
            : '';

        return trim($firstName.' '.$lastInitial) ?: 'Anonymous';
    }

    protected function reviewerInitials(?User $rater): string
    {
        if (! $rater) {
            return '??';
        }

        $first = strtoupper(substr(trim((string) ($rater->first_name ?: $rater->name)), 0, 1));
        $last = strtoupper(substr(trim((string) $rater->last_name), 0, 1));

        return $first.($last ?: '');
    }
}
