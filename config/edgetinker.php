<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default site settings
    |--------------------------------------------------------------------------
    | Used until a value is saved in Content Studio → Site settings.
    | Values marked [PLACEHOLDER] are shown as such on the site so they are
    | not mistaken for real contact details.
    */
    'settings' => [
        'site.name' => 'EdgeTinker',
        'site.tagline' => 'Embedded hardware, engineered to build on.',
        'site.logo_path' => null, // null = text-only wordmark

        'contact.email' => '[PLACEHOLDER] hello@example.com',
        'contact.phone' => '[PLACEHOLDER] +94 00 000 0000',
        'contact.address' => '[PLACEHOLDER] Street address, City, Sri Lanka',
        'contact.map_embed_url' => null,

        'social.linkedin' => null,
        'social.github' => null,
        'social.youtube' => null,
        'social.facebook' => null,
    ],

    /*
    | Where contact-form submissions are emailed (falls back to contact.email
    | when that has been set to a real address).
    */
    'contact_recipient' => env('CONTACT_RECIPIENT'),

    'home' => [
        'featured_limit' => 8,
        'slide_interval_ms' => 6000,
    ],

];
