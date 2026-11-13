<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /** Change the interface language: saved on the account, and in a cookie for signed-out pages. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(config('locales')))]]);

        $request->user()?->update(['locale' => $data['locale']]);

        return back()->withCookie(cookie()->forever('locale', $data['locale']));
    }
}
