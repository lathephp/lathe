<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController as FortifyTwoFactorController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Security page's two-factor card: enable, confirm, disable, the QR code, the secret and the recovery codes.
 */
class TwoFactorAuthenticationController
{
    use CallsFortify;

    public function store(Request $request): Response
    {
        return $this->callFortify($request, FortifyTwoFactorController::class, 'store');
    }

    public function confirm(Request $request): Response
    {
        return $this->callFortify($request, ConfirmedTwoFactorAuthenticationController::class, 'store');
    }

    public function destroy(Request $request): Response
    {
        return $this->callFortify($request, FortifyTwoFactorController::class, 'destroy');
    }

    public function qrCode(Request $request): Response
    {
        return $this->callFortify($request, TwoFactorQrCodeController::class, 'show');
    }

    public function secretKey(Request $request): Response
    {
        return $this->callFortify($request, TwoFactorSecretKeyController::class, 'show');
    }

    public function recoveryCodes(Request $request): Response
    {
        return $this->callFortify($request, RecoveryCodeController::class, 'index');
    }

    public function regenerateRecoveryCodes(Request $request): Response
    {
        $this->callFortify($request, RecoveryCodeController::class, 'store');

        // Fortify answers an empty 200; the card shows the new codes at once.
        return $this->recoveryCodes($request);
    }
}
