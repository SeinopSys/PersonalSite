<?php

declare(strict_types=1);

namespace App\Util;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Response
{
    public static function Fail(string $message = '', $data = [])
    {
        if (empty($message)) {
            $message = Auth::check() ? 'Insufficient permissions.' : '<p>You are not signed in (or your session expired), please sign in before continuing.</p>';
        }

        self::_respond(false, $message, $data);
    }

    public static function Success(string $message, $data = [])
    {
        self::_respond(true, $message, $data);
    }

    public static function Done(array $data = [])
    {
        self::_respond(true, '', $data);
    }

    private static function _respond(bool $status, string $message, $data)
    {
        header('Content-Type: application/json');
        $response = ['status' => $status];
        if (!empty($message)) {
            $response['message'] = $message;
        }
        if (!empty($data) && \is_array($data)) {
            $response = array_merge($data, $response);
        }
        echo JSON::Encode($response);
        Session::flush();
        App::terminate();
        exit;
    }
}
