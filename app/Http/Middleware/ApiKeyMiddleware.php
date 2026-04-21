<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Missing or invalid Authorization header. Expected: Bearer {API_KEY}',
            ], 401);
        }

        $apiKey = substr($authHeader, 7);

        if (empty(trim($apiKey))) {
            return response()->json([
                'error' => 'API key cannot be empty.',
            ], 401);
        }

        $user = User::where('api_key', $apiKey)->first();

        if (! $user) {
            return response()->json([
                'error' => 'Invalid API key.',
            ], 401);
        }

        // ── Account status check ──────────────────────────────────────────────
        if (! $user->is_active) {
            return response()->json([
                'error' => 'This account has been disabled. Please contact support.',
            ], 403);
        }

        // ── Domain binding check ──────────────────────────────────────────────
        // Only enforced when the client has registered a domain AND the request
        // sends an X-Site-Domain (or Origin) header we can compare against.
        if ($user->website_domain) {
            $requestDomain = $this->extractRequestDomain($request);

            if ($requestDomain !== null) {
                $registered = strtolower(trim($user->website_domain));
                if ($requestDomain !== $registered) {
                    return response()->json([
                        'error'             => 'Domain mismatch. This API key is bound to ' . $user->website_domain . '.',
                        'registered_domain' => $user->website_domain,
                        'request_domain'    => $requestDomain,
                    ], 403);
                }
            }
        }

        $request->attributes->set('authenticated_user', $user);
        $request->attributes->set('client_id', $user->client_id);

        return $next($request);
    }

    private function extractRequestDomain(Request $request): ?string
    {
        // Only enforce domain binding when the WordPress plugin explicitly sends
        // X-Site-Domain. Falling back to Origin/Referer would block dashboard
        // API calls whose Origin is the ShopAI platform itself (e.g. ngrok URL).
        $raw = $request->header('X-Site-Domain');

        if (! $raw) {
            return null;
        }

        $host = parse_url($raw, PHP_URL_HOST) ?? $raw;
        // Strip port and www.
        $host = preg_replace('/:\d+$/', '', $host);
        $host = preg_replace('/^www\./i', '', $host);
        return strtolower(trim($host));
    }
}
