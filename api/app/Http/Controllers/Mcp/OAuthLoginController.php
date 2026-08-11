<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OAuthLoginController extends Controller
{
    /**
     * Show the login form for the OAuth authorization flow.
     */
    public function showLogin(Request $request)
    {
        return view('oauth.login', [
            'intended' => $request->query('intended', '/'),
            'error' => null,
        ]);
    }

    /**
     * Handle the login form submission and redirect back to the authorize URL.
     */
    public function handleLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::guard('web')->attempt($credentials)) {
            return view('oauth.login', [
                'intended' => $request->input('intended', '/'),
                'error' => 'Invalid email or password.',
            ]);
        }

        return redirect($request->input('intended', '/'));
    }
}
