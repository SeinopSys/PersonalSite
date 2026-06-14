<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Show the application's login form.
     */
    public function showLoginForm()
    {
        return view('auth.login', [
            'title' => __('auth.login'),
        ]);
    }

    public function loggedOut(Request $request)
    {
        return response()->noContent();
    }

    /**
     * Called after credentials are verified and the user is logged in.
     * If the user has 2FA enabled, log them back out and require a TOTP
     * challenge before completing the login.
     */
    protected function authenticated(Request $request, User $user)
    {
        if (!$user->hasTwoFactorEnabled()) {
            return null;
        }

        Auth::logout();

        $request->session()->put('2fa_challenge', [
            'id' => $user->id,
            'remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('2fa.challenge');
    }
}
