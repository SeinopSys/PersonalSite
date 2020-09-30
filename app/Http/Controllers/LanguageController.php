<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function switchLang($lang)
    {
        if (\array_key_exists($lang, Config::get('languages'))) {
            Session::put('lang', $lang);
            if (Auth::check()) {
                Auth::user()->lang = $lang;
                Auth::user()->save();
            }
        }

        return Redirect::back();
    }
}
