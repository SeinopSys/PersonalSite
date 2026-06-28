<?php

namespace App\Http\Controllers;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthController extends Controller
{
    /**
     * Generate a new pending secret and store it in the session for confirmation.
     */
    public function setup(Request $request)
    {
        $google2fa = new Google2FA();

        $secret = $request->session()->get('2fa_pending_secret');

        if (!$secret) {
            $secret = $google2fa->generateSecretKey();
            $request->session()->put('2fa_pending_secret', $secret);
        }

        return redirect('/account');
    }

    /**
     * Build the QR code SVG and secret for a pending 2FA setup, if any.
     */
    public static function pendingSetup(Request $request, User $user): ?array
    {
        $secret = $request->session()->get('2fa_pending_secret');

        if (!$secret) {
            return null;
        }

        $google2fa = new Google2FA();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        $renderer = new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd());
        $qrCodeSvg = (new Writer($renderer))->writeString($qrCodeUrl);
        $qrCodeSvg = preg_replace('/^<\?xml.*\?>\s*/', '', $qrCodeSvg);

        return [
            'secret' => $secret,
            'qr_code_svg' => $qrCodeSvg,
        ];
    }

    /**
     * Confirm the pending secret with a TOTP code and enable 2FA.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $secret = $request->session()->get('2fa_pending_secret');

        if (!$secret) {
            throw ValidationException::withMessages([
                'code' => __('dashboard.2fa-no-pending-setup'),
            ]);
        }

        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey($secret, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => __('auth.2fa-invalid-code'),
            ]);
        }

        $user = Auth::user();
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = now();
        $user->save();

        $request->session()->forget('2fa_pending_secret');

        return redirect('/account')->with('success', __('dashboard.2fa-enabled'));
    }

    /**
     * Disable 2FA for the current user.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect('/account')->with('success', __('dashboard.2fa-disabled'));
    }
}
