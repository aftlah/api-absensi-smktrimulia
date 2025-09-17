<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request): ?string
    {
        // API harus return null → supaya balikin 401 JSON, bukan redirect
        return $request->expectsJson() ? null : route('login');
    }
}
