<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackConnectionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->attributes->get('authenticated_user');

        if ($user && $response->getStatusCode() < 400) {
            $user->update([
                'connection_status' => 'connected',
                'last_connected_at' => now(),
            ]);
        }

        return $response;
    }
}
