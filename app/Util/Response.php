<?php

declare(strict_types=1);

namespace App\Util;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class Response
{
    public static function Fail(string $message = '', $data = []): JsonResponse
    {
        if (empty($message)) {
            $message = Auth::check() ? 'Insufficient permissions.' : '<p>You are not signed in (or your session expired), please sign in before continuing.</p>';
        }

        return self::_respond(false, $message, $data);
    }

    public static function Success(string $message, $data = []): JsonResponse
    {
        return self::_respond(true, $message, $data);
    }

    public static function Done(array $data = []): JsonResponse
    {
        return self::_respond(true, '', $data);
    }

    private static function _respond(bool $status, string $message, $data): JsonResponse
    {
        $response = ['status' => $status];
        if (!empty($message)) {
            $response['message'] = $message;
        }
        if (!empty($data) && \is_array($data)) {
            $response = array_merge($data, $response);
        }

        return new JsonResponse($response, $status ? 200 : 500, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
