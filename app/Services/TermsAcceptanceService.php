<?php

namespace App\Services;

use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TermsAcceptanceService
{
    public function current(): TermsVersion
    {
        return TermsVersion::query()->where('is_current', true)->latest('effective_at')->firstOrFail();
    }

    public function accept(User $user, Request $request, ?string $requestedVersion = null, string $source = 'app'): TermsAcceptance
    {
        $terms = $this->current();

        if ($requestedVersion !== null && $requestedVersion !== $terms->version) {
            throw ValidationException::withMessages([
                'terms_version' => ['The accepted terms version is no longer current. Refresh the terms and try again.'],
            ]);
        }

        $acceptance = TermsAcceptance::firstOrCreate(
            ['user_id' => $user->id, 'terms_version_id' => $terms->id],
            [
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'source' => $source,
            ]
        );

        $user->forceFill(['terms_accepted_at' => $acceptance->accepted_at])->save();

        return $acceptance->load('termsVersion');
    }
}
