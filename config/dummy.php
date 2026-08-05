<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dummy Review Account
    |--------------------------------------------------------------------------
    |
    | A single seeded account used by the Play Store / App Store reviewers to
    | walk through the app without touching real member data. Its records are
    | flagged with `is_dummy` and are hidden from every real user and every
    | public feed. Turn this off once the app has cleared review.
    |
    */

    'enabled' => env('DUMMY_LOGIN_ENABLED', false),

    'phone' => env('DUMMY_LOGIN_PHONE', '8107856666'),

    'otp' => env('DUMMY_LOGIN_OTP', '9776'),

    'email' => env('DUMMY_LOGIN_EMAIL', 'dummy.member@rotary.local'),

];
