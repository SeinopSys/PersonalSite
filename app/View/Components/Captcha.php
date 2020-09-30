<?php

namespace App\View\Components;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\View\Component;

class Captcha extends Component
{
    /**
     * Any error messages
     *
     * @type MessageBag
     */
    public $errors;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($errors)
    {
        $this->errors = $errors;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.captcha');
    }
}
