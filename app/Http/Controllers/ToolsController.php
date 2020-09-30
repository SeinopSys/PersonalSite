<?php

namespace App\Http\Controllers;

class ToolsController extends Controller
{
    public function networking()
    {
        return view('networking', [
            'title' => __('global.networking'),
            'css' => ['networking'],
            'js' => ['networking'],
            'tags' => 'calculator,subnetting,subnets,subnet calculator,cidr,vlsm,ip,ipv4,ipv6,alhálózat,alhálózat számolás,prefix lista,prefixes,prefix list,előtag lista,előtagok'
        ]);
    }

    public function imagecalc()
    {
        return view('imagecalc', [
            'title' => __('global.imagecalc'),
            'css' => ['imagecalc'],
            'js' => ['imagecalc'],
            'tags' => 'image,picture,calculator,aspect ratio,resolution,scaling'
        ]);
    }
}
