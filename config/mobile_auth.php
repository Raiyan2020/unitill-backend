<?php

return [

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
    | While testing, every login OTP is this code. Set the env var to an empty
    | value in production so a random 6-digit code is generated instead.
    */
    'login_otp_test_code' => env('MOBILE_LOGIN_OTP_TEST_CODE', '123456'),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL (days)
    |--------------------------------------------------------------------------
    */
    'refresh_token_ttl' => (int) env('MOBILE_REFRESH_TOKEN_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Biometric Token TTL (days)
    |--------------------------------------------------------------------------
    */
    'biometric_token_ttl' => (int) env('MOBILE_BIOMETRIC_TOKEN_TTL', 90),

];
