<?php

namespace App\Http\Middleware;

use App\Util\Permission;
use Closure;
use Illuminate\Http\Request;

class RequireUser
{
    public function handle(Request $request, Closure $next)
    {
        if (Permission::Insufficient('user')) {
            abort(403);
        }

        return $next($request);
    }
}
