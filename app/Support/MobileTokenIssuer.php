<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @deprecated Use MobileAuthTokenService instead.
 */
class MobileTokenIssuer
{
    public static function issue(User $user, Request $request): string
    {
        $tokens = app(MobileAuthTokenService::class)->issue($user, $request);

        return $tokens['access_token'];
    }
}
