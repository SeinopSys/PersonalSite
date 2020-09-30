<?php

return [
    'enabled' => env('HCAPTCHA_ENABLED', false),

    'sitekey' => env('HCAPTCHA_SITEKEY', null),

    'secret' => env('HCAPTCHA_SECRET', null),
];
