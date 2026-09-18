<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every account must confirm two-factor authentication before using the application.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('cyber.require_two_factor') && $user !== null && $user->two_factor_confirmed_at === null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('Set up two-factor authentication to continue.')]);

            return to_route('security.edit');
        }

        return $next($request);
    }
}
