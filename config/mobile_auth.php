<?php

return [

    /* Student verification is renewed from each user's own verification date. */
    'student_reverification_months' => (int) env('STUDENT_REVERIFICATION_MONTHS', 12),

    /* Users remain active for this many days after verification becomes due. */
    'student_reverification_grace_days' => (int) env('STUDENT_REVERIFICATION_GRACE_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Access Token TTL (minutes)
    |--------------------------------------------------------------------------
    */
    'access_token_ttl' => (int) env('MOBILE_ACCESS_TOKEN_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | V2 Access Token TTL (minutes)
    |--------------------------------------------------------------------------
    | Used by the v2 OTP login flow. Defaults to 30 days (43200 minutes).
    */
    'v2_access_token_ttl' => (int) env('MOBILE_V2_ACCESS_TOKEN_TTL', 43200),

    /*
    |--------------------------------------------------------------------------
    | Login OTP TTL (minutes)
    |--------------------------------------------------------------------------
    */
    'login_otp_ttl' => (int) env('MOBILE_LOGIN_OTP_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Login OTP resend cooldown (seconds)
    |--------------------------------------------------------------------------
    */
    'login_otp_resend_cooldown' => (int) env('MOBILE_LOGIN_OTP_RESEND_COOLDOWN', 60),

    /*
    |--------------------------------------------------------------------------
    | Login OTP fixed test code
    |--------------------------------------------------------------------------
    | Pins every login OTP to one value so automated tests and manual QA do not
    | need to read the mail queue.
    |
    | This defaulted to '123456', which meant that unless the environment
    | explicitly set the variable to an empty string, that code authenticated
    | as ANY account — a full authentication bypass shipping in the default
    | configuration. The default is now null, and the V2 controller only honours
    | the value in the testing environment, so setting it in production has no
    | effect either.
    */
    'login_otp_test_code' => env('MOBILE_LOGIN_OTP_TEST_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL (days)
    |--------------------------------------------------------------------------
    | Longer than the 12-month verification period plus its 60-day grace window.
    | Access tokens remain short-lived and are rotated normally; student status
    | is enforced by the independent annual verification lifecycle.
    */
    'refresh_token_ttl' => (int) env('MOBILE_REFRESH_TOKEN_TTL', 425),

    /*
    |--------------------------------------------------------------------------
    | Biometric Token TTL (days)
    |--------------------------------------------------------------------------
    */
    'biometric_token_ttl' => (int) env('MOBILE_BIOMETRIC_TOKEN_TTL', 90),

];
