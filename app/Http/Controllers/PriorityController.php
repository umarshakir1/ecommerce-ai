<?php

namespace App\Http\Controllers;

use App\Models\ClientPriority;
use App\Services\PriorityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * PriorityController
 *
 * CRUD API for managing a client's attribute priority rules.
 * All routes require the ApiKeyMiddleware — client_id is always
 * read from the authenticated user, never from the request body.
 *
 * Routes (prefix: /api/priorities):
 *   GET    /          – list all rules for this client
 *   POST   /          – create a new rule
 *   PUT    /{id}      – update a rule
 *   DELETE /{id}      – delete a rule
 *   DELETE /          – delete ALL rules for this client
 */
class PriorityController extends Controller
{
    public function __construct(private readonly PriorityService $priorityService) {}

    // -------------------------------------------------------------------------
    // LIST
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $clientId = $request->attributes->get('client_id');

        $rules = ClientPriority::where('client_id', $clientId)
            ->orderBy('attribute_type')
            ->orderByDesc('boost_weight')
            ->get(['id', 'attribute_type', 'attribute_value', 'boost_weight', 'created_at']);

        return response()->json(['priorities' => $rules]);
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'attribute_type'  => ['required', 'string', 'in:' . implode(',', ClientPriority::VALID_TYPES)],
            'attribute_value' => ['required', 'string', 'max:100'],
            'boost_weight'    => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $clientId = $request->attributes->get('client_id');

        $rule = ClientPriority::updateOrCreate(
            [
                'client_id'       => $clientId,
                'attribute_type'  => $request->input('attribute_type'),
                'attribute_value' => strtolower(trim($request->input('attribute_value'))),
            ],
            [
                'boost_weight' => $request->input('boost_weight', 0.5),
            ]
        );

        $this->priorityService->flushCache($clientId);

        return response()->json([
            'message'  => 'Priority rule saved.',
            'priority' => $rule,
        ], 201);
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'boost_weight' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $clientId = $request->attributes->get('client_id');

        $rule = ClientPriority::where('client_id', $clientId)->findOrFail($id);
        $rule->update(['boost_weight' => $request->input('boost_weight')]);

        $this->priorityService->flushCache($clientId);

        return response()->json([
            'message'  => 'Priority rule updated.',
            'priority' => $rule,
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE ONE
    // -------------------------------------------------------------------------

    public function destroy(Request $request, int $id): JsonResponse
    {
        $clientId = $request->attributes->get('client_id');

        $rule = ClientPriority::where('client_id', $clientId)->findOrFail($id);
        $rule->delete();

        $this->priorityService->flushCache($clientId);

        return response()->json(['message' => 'Priority rule deleted.']);
    }

    // -------------------------------------------------------------------------
    // DELETE ALL
    // -------------------------------------------------------------------------

    public function destroyAll(Request $request): JsonResponse
    {
        $clientId = $request->attributes->get('client_id');

        ClientPriority::where('client_id', $clientId)->delete();
        $this->priorityService->flushCache($clientId);

        return response()->json(['message' => 'All priority rules cleared.']);
    }
}
