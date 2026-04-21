<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(private readonly ClientService $clientService) {}

    // -------------------------------------------------------------------------
    // POST /api/auth/register
    // Creates account WITHOUT api_key. Returns a setup_token used to bind
    // the domain and generate the real api_key in a second step.
    // -------------------------------------------------------------------------

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $setupToken = bin2hex(random_bytes(32));

        $user = User::create([
            'name'        => $request->input('name'),
            'email'       => $request->input('email'),
            'password'    => Hash::make($request->input('password')),
            'client_id'   => $this->clientService->generateClientId(),
            'setup_token' => $setupToken,
        ]);

        return response()->json([
            'message'     => 'Registration successful. Please set your website domain to activate your API key.',
            'setup_token' => $setupToken,
            'user'        => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'client_id' => $user->client_id,
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/login
    // Returns api_key if setup is complete, or setup_token if still pending.
    // -------------------------------------------------------------------------

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'error' => 'Your account has been disabled. Please contact support.',
            ], 403);
        }

        $payload = [
            'message' => 'Login successful.',
            'user'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'client_id'      => $user->client_id,
                'website_domain' => $user->website_domain,
            ],
        ];

        if ($user->api_key) {
            $payload['api_key']          = $user->api_key;
            $payload['setup_complete']   = true;
        } else {
            $payload['setup_token']      = $user->setup_token;
            $payload['setup_complete']   = false;
            $payload['message']          = 'Login successful. Please complete domain setup to activate your API key.';
        }

        return response()->json($payload);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/setup-domain   (public — authenticated via setup_token)
    // Binds the client's website domain and generates their api_key.
    // -------------------------------------------------------------------------

    public function setupDomain(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'setup_token'    => ['required', 'string'],
            'website_domain' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('setup_token', $request->input('setup_token'))->first();

        if (! $user) {
            return response()->json([
                'error' => 'Invalid or expired setup token.',
            ], 401);
        }

        $domain = $this->normalizeDomain($request->input('website_domain'));
        $apiKey = $this->clientService->generateApiKey();

        $user->update([
            'website_domain' => $domain,
            'api_key'        => $apiKey,
            'setup_token'    => null,
            'is_active'      => true,
        ]);

        return response()->json([
            'message'        => 'Domain registered and API key generated successfully.',
            'api_key'        => $apiKey,
            'website_domain' => $domain,
            'user'           => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'client_id' => $user->client_id,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PUT /api/auth/domain   (requires ApiKeyMiddleware)
    // Allows an already-active client to update their registered domain.
    // -------------------------------------------------------------------------

    public function updateDomain(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'website_domain' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var User $user */
        $user   = $request->attributes->get('authenticated_user');
        $domain = $this->normalizeDomain($request->input('website_domain'));
        $user->update(['website_domain' => $domain]);

        return response()->json([
            'message'        => 'Domain updated successfully.',
            'website_domain' => $domain,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/auth/me  (requires ApiKeyMiddleware)
    // -------------------------------------------------------------------------

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('authenticated_user');

        return response()->json([
            'user'    => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'client_id'        => $user->client_id,
                'website_domain'   => $user->website_domain,
                'connection_status'=> $user->connection_status,
                'last_connected_at'=> $user->last_connected_at?->toISOString(),
                'is_active'        => $user->is_active,
            ],
            'api_key' => $user->api_key,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/validate-key  (requires ApiKeyMiddleware)
    // Used by the WordPress plugin to test the connection.
    // -------------------------------------------------------------------------

    public function validateKey(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('authenticated_user');

        return response()->json([
            'valid'          => true,
            'message'        => 'API key is valid.',
            'website_domain' => $user->website_domain,
            'user'           => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'client_id' => $user->client_id,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        // Strip protocol
        $domain = preg_replace('#^https?://#', '', $domain);
        // Strip path, query, fragment
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];
        // Strip www.
        $domain = preg_replace('/^www\./i', '', $domain);
        // Strip port
        $domain = preg_replace('/:\d+$/', '', $domain);
        return strtolower(trim($domain));
    }
}
