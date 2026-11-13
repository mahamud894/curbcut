<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public const ABILITIES = ['read', 'scan'];

    public function index(Request $request): Response
    {
        return Inertia::render('settings/api-tokens', [
            'tokens' => $request->user()->tokens()->latest('id')->get()->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
            ]),
            'newToken' => $request->session()->get('newToken'),
            'apiUrl' => url('/api/v1'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'can_scan' => ['boolean'],
        ]);

        $abilities = $request->boolean('can_scan') ? self::ABILITIES : ['read'];
        $token = $request->user()->createToken($data['name'], $abilities);

        return back()->with('newToken', $token->plainTextToken)->with('success', 'Token created. Copy it now: it will not be shown again.');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back()->with('success', 'Token revoked.');
    }
}
