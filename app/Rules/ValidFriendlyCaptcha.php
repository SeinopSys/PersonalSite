<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ValidFriendlyCaptcha implements Rule
{
  public function passes($attribute, $value)
  {
    if (config('captcha.enabled') !== true) {
      return true;
    }

    $data = array(
      'sitekey' => config('captcha.sitekey'),
      'response' => $value
    );

    $response = Http::asForm()->withHeaders([
      'X-API-Key' => config('captcha.api-key'),
    ])->post('https://global.frcapi.com/api/v2/captcha/siteverify', $data);

    if (!$response->successful()) {
      Log::error("FriendlyCaptcha validation failed (HTTP {$response->status()}), response body:\n$response->body()");
      return false;
    }

    $responseData = $response->json();

    return isset($responseData['success']) && $responseData['success'] === true;
  }

  public function message()
  {
    return trans('validation.captcha');
  }
}
