<?php

namespace App\Http\Controllers;

class InlinerController extends Controller {
  public function index() {
    return view('inliner', [
      'title' => __('global.inliner'),
      'css' => ['inliner'],
      'js' => ['inliner'],
    ]);
  }
}
