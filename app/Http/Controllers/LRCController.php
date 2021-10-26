<?php

namespace App\Http\Controllers;

class LRCController extends Controller {
  public function index() {
    return view('lrc', [
      'css' => ['lrc'],
      'js' => ['lrc'],
    ]);
  }
}
