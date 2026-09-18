<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerLinked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_staff && ! $user->customer_id) {
            auth()->logout();

            return redirect()->route('filament.portal.auth.login')
                ->withErrors(['email' => 'Your account is not yet linked to a customer account. Please contact us.']);
        }

        return $next($request);
    }
}
