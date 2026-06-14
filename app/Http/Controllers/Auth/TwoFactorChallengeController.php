<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function show(Request $request)
    {
        if (!$request->session()->has('2fa_challenge')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'title' => __('auth.2fa-title'),
        ]);
    }

    public function verify(Request $request)
    {
        $challenge = $request->session()->get('2fa_challenge');

        if (!$challenge) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = User::find($challenge['id']);

        if (!$user || !$user->hasTwoFactorEnabled() ||
            !(new Google2FA())->verifyKey($user->two_factor_secret, $request->input('code'))) {
            return back()->withErrors(['code' => __('auth.2fa-invalid-code')]);
        }

        $request->session()->forget('2fa_challenge');
        $request->session()->regenerate();

        Auth::login($user, $challenge['remember']);

        return redirect()->intended('/dashboard');
    }
}
