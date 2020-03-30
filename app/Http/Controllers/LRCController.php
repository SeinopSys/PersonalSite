<?php

namespace App\Http\Controllers;

class LRCController extends Controller
{
    public function index()
    {
        return view('lrc', [
            'css' => ['lrc'],
            'js' => ['jquery.ba-throttle-debounce', 'Blob', 'FileSaver', 'jsmediatags', 'lrc'],
        ]);
    }
}
