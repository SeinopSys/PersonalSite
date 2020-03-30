<?php

namespace App\Http\Controllers;

class NetSalaryController extends Controller
{
    public function index()
    {
        return view('netsalary', [
            'title' => __('global.netsalary'),
            'css' => ['netsalary'],
            'js' => ['netsalary'],
        ]);
    }
}
