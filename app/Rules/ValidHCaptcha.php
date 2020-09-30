<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;

class ValidHCaptcha implements Rule
{
    public function passes($attribute, $value)
    {
        if (config('hcaptcha.enabled') !== true) {
            return true;
        }

        $data = array(
            'secret' => config('hcaptcha.secret'),
            'response' => $value
        );

        $response = Http::asForm()->post('https://hcaptcha.com/siteverify', $data);

        if (!$response->successful()) {
            return false;
        }

        $responseData = $response->json();

        return !empty($responseData['success']);
    }

    public function message()
    {
        return trans('validation.hcaptcha');
    }
}
