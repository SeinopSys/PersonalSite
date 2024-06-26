<?php

return [
    'enabled' => env('CAPTCHA_ENABLED', false),

    'sitekey' => env('FRIENDLYCAPTCHA_SITEKEY', null),

    'api-key' => env('FRIENDLYCAPTCHA_API_KEY', null),
];
