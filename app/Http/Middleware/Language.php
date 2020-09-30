<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Teto\HTTP\AcceptLanguage;

class Language
{
    public const DEFAULT_LANGUAGE = 'en';

    private function languageExists(string $code, array $languages): bool
    {
        return !empty($code) && \array_key_exists($code, $languages);
    }

    public function handle($request, Closure $next)
    {
        $locale_set = false;
        $languages = Config::get('languages');
        $loaded_lang = null;
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if ($this->languageExists($user->lang, $languages)) {
                $loaded_lang = $user->lang;
            } else {
                $user->lang = self::DEFAULT_LANGUAGE;
                $user->save();
            }
        }
        if ($loaded_lang === null && Session::has('lang')) {
            $session_lang = Session::get('lang');
            if ($this->languageExists($session_lang, $languages)) {
                $loaded_lang = $session_lang;
            }
        }
        if (!empty($loaded_lang)) {
            App::setLocale($loaded_lang);
        } else {
            $default_lang = self::DEFAULT_LANGUAGE;
            foreach (AcceptLanguage::get() as $accepts) {
                if (!\array_key_exists($accepts['language'], $languages)) {
                    continue;
                }

                $default_lang = $accepts['language'];
                break;
            }
            Session::put('lang', $default_lang);
            App::setLocale($default_lang);
        }

        return $next($request);
    }
}
