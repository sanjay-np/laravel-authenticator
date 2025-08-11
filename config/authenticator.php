<?php

// config for LaravelVerified
return [
    /*
    |--------------------------------------------------------------------------
    | Default TOTP Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the default behavior of TOTP generation and
    | verification in your application.
    |
    */

    'totp' => [
        /*
         * The default period (in seconds) for TOTP codes
         */
        'period' => 60,

        /*
         * The number of digits in the TOTP code
         */
        'digits' => 6,

        /*
         * The hash algorithm to use (sha1, sha256, sha512)
         */
        'algorithm' => 'sha1',

        /*
         * The window of periods to check for validation (allows for clock drift)
         */
        'window' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for QR code generation and display
    |
    */

    'qr_code' => [
        /*
         * QR code size in pixels
         */
        'size' => 200,

        /*
         * QR code margin
         */
        'margin' => 10,

        /*
         * QR code format (png, svg)
         */
        'format' => 'png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade Shortcode Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Blade shortcodes
    |
    */

    'shortcodes' => [
        /*
         * Enable Blade shortcodes
         */
        'enabled' => true,

        /*
         * Default shortcode tag
         */
        'tag' => 'totp',
    ],
];
