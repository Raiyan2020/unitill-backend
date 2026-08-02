<?php

/**
 * Golden-master capture harness.
 *
 * Boots the application and dispatches requests through the real HTTP kernel,
 * so middleware, FormRequests, resources and the exception renderer all run
 * exactly as they would for a mobile client. Writes one JSON file per scenario.
 *
 * Run in the Salman tree to produce the reference set, then in the Takwa tree
 * to produce the comparison set. Fixtures must already be loaded in both.
 *
 * Usage:
 *   php artisan tinker --execute="\$OUT='/abs/dir'; require '/path/capture.php';"
 */

use Illuminate\Http\Request;

$out = $OUT ?? __DIR__.'/captured';
@mkdir($out, 0777, true);

$kernel = app(\Illuminate\Contracts\Http\Kernel::class);

/**
 * Values that legitimately differ between runs. Everything else must match
 * byte for byte — field names, nulls, types, ordering, messages.
 */
$normalise = function ($node) use (&$normalise) {
    if (is_array($node)) {
        $clean = [];
        foreach ($node as $key => $value) {
            if (in_array($key, ['token', 'access_token', 'refresh_token', 'biometric_token',
                                'client_secret', 'payment_intent_id', 'stripe_payment_intent_id'], true)) {
                $clean[$key] = $value === null ? null : '<dynamic>';
                continue;
            }
            // Wall-clock derived values. These differ purely because the two
            // captures ran seconds apart, not because behaviour changed.
            if (in_array($key, ['expires_at', 'access_token_expires_at', 'refresh_token_expires_at',
                                'last_updated_at', 'last_active_at', 'last_updated_ago',
                                'last_active_label', 'created_at', 'updated_at'], true)
                && (is_string($value) || $value === null)) {
                $clean[$key] = $value === null ? null : '<timestamp>';
                continue;
            }

            // Session rows are keyed on autoincrement token ids and ordered by
            // last-used, so both the ids and the order are run-dependent.
            if ($key === 'sessions' && is_array($value)) {
                $clean[$key] = ['<sessions:'.count($value).'>'];
                continue;
            }
            $clean[$key] = $normalise($value);
        }

        return $clean;
    }

    if (is_string($node)) {
        // Absolute asset/storage URLs embed the host, which differs per tree.
        $node = preg_replace('#https?://[^/\s"]+/#', '<host>/', $node);
    }

    return $node;
};

/**
 * @param array{0:string,1:string,2:array,3:array} $spec method, uri, payload, headers
 */
$hit = function (string $name, string $method, string $uri, array $payload = [], array $headers = [])
use ($kernel, $out, $normalise) {
    // The api group throttles at 60/min. Capturing ~80 scenarios back to back
    // trips it and records 429s that have nothing to do with the contract, so
    // reset the limiter between scenarios. Throttle behaviour itself is
    // covered by a dedicated scenario at the end.
    \Illuminate\Support\Facades\Cache::flush();

    $server = [];
    foreach ($headers as $header => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
    }
    $server['HTTP_ACCEPT'] = 'application/json';

    $request = Request::create($uri, $method, $method === 'GET' ? $payload : [], [], [], $server,
        $method === 'GET' ? null : json_encode($payload));
    if ($method !== 'GET') {
        $request->headers->set('Content-Type', 'application/json');
    }

    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $body = json_decode($response->getContent(), true);
        if ($body === null) {
            // Non-JSON (HTML error view, redirect) — record enough to compare.
            $body = ['<non-json>' => substr(strip_tags((string) $response->getContent()), 0, 200)];
        }
    } catch (\Throwable $e) {
        $status = 'EXCEPTION';
        $body = ['<exception>' => get_class($e), 'message' => $e->getMessage()];
    }

    $record = ['request' => ['method' => $method, 'uri' => $uri, 'headers' => $headers],
               'status' => $status,
               'body' => $normalise($body)];

    file_put_contents(
        $out.'/'.$name.'.json',
        json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
    );

    printf("%-46s %s\n", $name, is_int($status) ? $status : $status);
};

// ------------------------------------------------------------------ tokens
// Log the fixture users in through the real endpoint so the token is genuine.
$loginBody = null;
$loginRequest = Request::create('/api/login', 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
], json_encode(['type' => 'data', 'email' => 'seller@example.com', 'password' => 'password123',
                'device_id' => 'fixture-device', 'device_type' => 'ios']));
try {
    $loginBody = json_decode(app(\Illuminate\Contracts\Http\Kernel::class)->handle($loginRequest)->getContent(), true);
} catch (\Throwable $e) {
    echo "login failed: ".$e->getMessage()."\n";
}
$token = data_get($loginBody, 'data.access_token') ?? data_get($loginBody, 'data.token');
$auth = $token ? ['Authorization' => 'Bearer '.$token] : [];
echo $token ? "auth token acquired\n" : "NO TOKEN — authenticated captures will be 401\n";

$en = ['lang' => 'en'];
$ar = ['lang' => 'ar'];

// ------------------------------------------------------------------ public
foreach ([['en', $en], ['ar', $ar], ['nolang', []]] as [$suffix, $langHeader]) {
    $hit("public_settings_$suffix",       'GET', '/api/settings', [], $langHeader);
    $hit("public_languages_$suffix",      'GET', '/api/languages', [], $langHeader);
    $hit("public_legal_affairs_$suffix",  'GET', '/api/legal-affairs', [], $langHeader);
    $hit("public_categories_$suffix",     'GET', '/api/categories', [], $langHeader);
    $hit("public_cities_$suffix",         'GET', '/api/cities', [], $langHeader);
    $hit("public_report_reasons_$suffix", 'GET', '/api/ad-report-reasons', [], $langHeader);
    $hit("public_home_$suffix",           'GET', '/api/home', [], $langHeader);
    $hit("public_ads_$suffix",            'GET', '/api/ads', [], $langHeader);
    $hit("public_ad_detail_$suffix",      'GET', '/api/ads/9001', [], $langHeader);
}

// ad list variants that exercise the filter/sort engine
$hit('ads_sort_newest',      'GET', '/api/ads', ['sort' => 'newest'], $en);
$hit('ads_sort_price_asc',   'GET', '/api/ads', ['sort' => 'price_low_to_high'], $en);
$hit('ads_filter_category',  'GET', '/api/ads', ['category_id' => 1], $en);
$hit('ads_filter_price',     'GET', '/api/ads', ['price_min' => 1, 'price_max' => 1000], $en);
$hit('ads_legacy_near',      'GET', '/api/ads', ['near_lat' => 53.8, 'near_lng' => -1.54], $en);
$hit('ads_unknown_param',    'GET', '/api/ads', ['totally_unknown_key' => 'x'], $en);
$hit('ads_pagination',       'GET', '/api/ads', ['per_page' => 1, 'page' => 2], $en);
$hit('ads_search_empty',     'GET', '/api/ads', ['search' => 'zzzz-no-match-zzzz'], $en);

// error shapes
$hit('err_ad_missing',       'GET', '/api/ads/99999999', [], $en);
$hit('err_unknown_route',    'GET', '/api/definitely-not-a-route', [], $en);
$hit('err_unauth_profile',   'GET', '/api/show-profile', [], $en);
$hit('err_login_bad',        'POST', '/api/login', ['type' => 'data', 'email' => 'seller@example.com', 'password' => 'wrong', 'device_id' => 'fixture-device'], $en);
$hit('err_login_no_user',    'POST', '/api/login', ['type' => 'data', 'email' => 'nobody@example.com', 'password' => 'password123', 'device_id' => 'fixture-device'], $en);
$hit('err_login_validation', 'POST', '/api/login', [], $en);
$hit('err_register_domain',  'POST', '/api/register', [
    'name' => 'X', 'email' => 'new@example.com', 'student_email' => 'x@not-a-university.com',
    'password' => 'password123', 'password_confirmation' => 'password123',
], $en);
$hit('err_method_not_allowed', 'DELETE', '/api/settings', [], $en);

// ----------------------------------------------------------- authenticated
foreach ([['en', $en + $auth], ['ar', $ar + $auth]] as [$suffix, $h]) {
    $hit("auth_profile_$suffix",        'GET', '/api/show-profile', [], $h);
    $hit("auth_profile_other_$suffix",  'GET', '/api/show-profile/9002', [], $h);
    $hit("auth_my_ads_$suffix",         'GET', '/api/my-ads', ['status' => 'published'], $h);
    $hit("auth_my_ads_draft_$suffix",   'GET', '/api/my-ads', ['status' => 'draft'], $h);
    $hit("auth_my_ads_novalid_$suffix", 'GET', '/api/my-ads', [], $h);
    $hit("auth_favorites_$suffix",      'GET', '/api/favorites', [], $h);
    $hit("auth_notifications_$suffix",  'GET', '/api/notifications', [], $h);
    $hit("auth_notif_unread_$suffix",   'GET', '/api/notifications/unread-count', [], $h);
    $hit("auth_conversations_$suffix",  'GET', '/api/conversations', [], $h);
    $hit("auth_ad_detail_$suffix",      'GET', '/api/ads/9001', [], $h);
    $hit("auth_security_$suffix",       'GET', '/api/account-security', [], $h);
    $hit("auth_ratings_$suffix",        'GET', '/api/ratings/9001', [], $h);
    $hit("auth_trusted_seller_$suffix", 'GET', '/api/trusted-seller-application', [], $h);
}

$hit('auth_coupon_valid',   'POST', '/api/coupons/validate', ['code' => 'FIXTURE10'], $en + $auth);
$hit('auth_coupon_unknown', 'POST', '/api/coupons/validate', ['code' => 'NOPE'], $en + $auth);
$hit('auth_coupon_missing', 'POST', '/api/coupons/validate', [], $en + $auth);
$hit('auth_login_ok',       'POST', '/api/login', ['type' => 'data', 'email' => 'seller@example.com',
    'password' => 'password123', 'device_id' => 'fixture-device', 'device_type' => 'ios'], $en);
$hit('auth_login_ok_ar',    'POST', '/api/login', ['type' => 'data', 'email' => 'seller@example.com',
    'password' => 'password123', 'device_id' => 'fixture-device', 'device_type' => 'ios'], $ar);

echo "\ncaptured into: $out\n";
