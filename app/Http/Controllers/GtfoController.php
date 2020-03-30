<?php

namespace App\Http\Controllers;

use App\Http\Requests;

class GtfoController extends Controller
{
    public function gone()
    {
        abort(410);
    }
}
