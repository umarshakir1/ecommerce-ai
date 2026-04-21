<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    // -------------------------------------------------------------------------
    // AUTH
    // -------------------------------------------------------------------------

    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            if (! Auth::user()->is_admin) {
                Auth::logout();
                return back()->withErrors(['email' => 'You do not have admin access.'])->withInput();
            }
            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    // -------------------------------------------------------------------------
    // DASHBOARD VIEW
    // -------------------------------------------------------------------------

    public function dashboard(): View
    {
        return view('admin.dashboard');
    }

    // -------------------------------------------------------------------------
    // JSON API — STATS
    // -------------------------------------------------------------------------

    public function stats(): JsonResponse
    {
        $totalClients      = User::where('is_admin', false)->count();
        $totalProducts     = Product::count();
        $productsWithEmbed = Product::whereNotNull('embedding')->count();
        $newThisMonth      = User::where('is_admin', false)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return response()->json([
            'total_clients'       => $totalClients,
            'total_products'      => $totalProducts,
            'products_with_embed' => $productsWithEmbed,
            'new_this_month'      => $newThisMonth,
        ]);
    }

    // -------------------------------------------------------------------------
    // JSON API — CLIENT LIST
    // -------------------------------------------------------------------------

    public function clients(Request $request): JsonResponse
    {
        $search = trim($request->input('search', ''));

        $clients = User::where('is_admin', false)
            ->withCount('products')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('client_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (User $u) => [
                'id'                => $u->id,
                'name'              => $u->name,
                'email'             => $u->email,
                'client_id'         => $u->client_id,
                'api_key_masked'    => $u->api_key ? ('••••••' . substr($u->api_key, -8)) : '(not generated)',
                'website_domain'    => $u->website_domain,
                'is_active'         => $u->is_active,
                'connection_status' => $u->connection_status ?? 'not_connected',
                'last_connected_at' => $u->last_connected_at?->diffForHumans(),
                'products_count'    => $u->products_count,
                'registered_at'     => $u->created_at->format('M d, Y'),
                'registered_ago'    => $u->created_at->diffForHumans(),
            ]);

        return response()->json(['clients' => $clients]);
    }

    // -------------------------------------------------------------------------
    // JSON API — SINGLE CLIENT DETAIL
    // -------------------------------------------------------------------------

    public function showClient(int $id): JsonResponse
    {
        $user = User::where('is_admin', false)->findOrFail($id);

        $products = Product::where('client_id', $user->client_id)
            ->select('id', 'name', 'category', 'price', 'in_stock', 'popularity', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn (Product $p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'category'   => $p->category,
                'price'      => number_format($p->price, 2),
                'in_stock'   => $p->in_stock,
                'popularity' => $p->popularity,
                'added'      => $p->created_at->format('M d, Y'),
            ]);

        $productsCount     = Product::where('client_id', $user->client_id)->count();
        $embeddedCount     = Product::where('client_id', $user->client_id)->whereNotNull('embedding')->count();
        $inStockCount      = Product::where('client_id', $user->client_id)->where('in_stock', true)->count();

        return response()->json([
            'client' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'client_id'         => $user->client_id,
                'api_key'           => $user->api_key ?? '(not generated yet)',
                'website_domain'    => $user->website_domain ?? '(not set)',
                'is_active'         => $user->is_active,
                'connection_status' => $user->connection_status ?? 'not_connected',
                'last_connected_at' => $user->last_connected_at?->format('M d, Y H:i') ?? 'Never',
                'products_count'    => $productsCount,
                'embedded_count'    => $embeddedCount,
                'in_stock_count'    => $inStockCount,
                'registered_at'     => $user->created_at->format('M d, Y H:i'),
                'registered_ago'    => $user->created_at->diffForHumans(),
            ],
            'recent_products' => $products,
        ]);
    }

    // -------------------------------------------------------------------------
    // JSON API — DELETE CLIENT
    // -------------------------------------------------------------------------

    public function deleteClient(int $id): JsonResponse
    {
        $user = User::where('is_admin', false)->findOrFail($id);

        Product::where('client_id', $user->client_id)->delete();
        $user->delete();

        return response()->json(['message' => 'Client and all associated products have been deleted.']);
    }

    // -------------------------------------------------------------------------
    // JSON API — TOGGLE CLIENT ACTIVE STATUS
    // -------------------------------------------------------------------------

    public function toggleActive(int $id): JsonResponse
    {
        $user = User::where('is_admin', false)->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'enabled' : 'disabled';

        return response()->json([
            'message'   => "Client {$status} successfully.",
            'is_active' => $user->is_active,
        ]);
    }

    // -------------------------------------------------------------------------
    // JSON API — REGENERATE API KEY
    // -------------------------------------------------------------------------

    public function regenerateKey(int $id): JsonResponse
    {
        $user    = User::where('is_admin', false)->findOrFail($id);
        $newKey  = bin2hex(random_bytes(32));
        $user->update(['api_key' => $newKey]);

        return response()->json([
            'message' => 'API key regenerated successfully.',
            'api_key' => $newKey,
        ]);
    }
}
