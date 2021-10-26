<?php

namespace App\Http\Controllers;

class AboutController extends Controller {
  public function gotoIndex() {
    return redirect()->route('about');
  }

  public function index() {
    return view('about', [
      'css' => ['about'],
      'js' => ['about'],
      'email' => 'david@seinopsys.dev',
    ]);
  }
}
