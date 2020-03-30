<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Support\Facades\Config;

class AboutController extends Controller
{
    public function gotoIndex()
    {
        return redirect()->route('about');
    }

    public function index()
    {
        return view('about', [
            'css' => ['about'],
            'js' => ['moment-timezone', 'about'],
            'email' => 'david@seinopsys.dev',
        ]);
    }
}
