<?php

namespace App\Http\Controllers;

class GtfoController extends Controller
{
    public function gone()
    {
        abort(410);
    }
}
