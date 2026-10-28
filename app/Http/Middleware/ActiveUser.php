<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveUser
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()?->active || ! $r->user()?->role) {
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Hesap erişimi kapalı.']);
        }

        return $next($r);
    }
}
