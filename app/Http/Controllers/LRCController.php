<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LRCController extends Controller {
  public function index(Request $request) {
    $lrcSiteUrl = config('app.lrc_site_url');
    if (!empty($lrcSiteUrl) && $request->input('redirect') !== 'false') {
        return redirect()->away($lrcSiteUrl);
    }

    return view('lrc', [
      'css' => ['lrc'],
      'js' => ['lrc'],
    ]);
  }
}
