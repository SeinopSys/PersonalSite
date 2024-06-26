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
      'solution' => $value,
      'secret' => config('captcha.api-key'),
      'sitekey' => config('captcha.sitekey'),
    );

    $response = Http::asForm()->post('https://api.friendlycaptcha.com/api/v1/siteverify', $data);

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
