<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return Auth::check() ? redirect(Auth::user()->homeUrl()) : view('auth.login');
    }

    public function store(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($cred, $r->boolean('remember'))) {
            return back()->withInput($r->only('email'))->withErrors(['email' => 'Email atau kata sandi salah.']);
        }
        $r->session()->regenerate();
        return redirect(Auth::user()->homeUrl());
    }

    public function destroy(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('login');
    }
}
