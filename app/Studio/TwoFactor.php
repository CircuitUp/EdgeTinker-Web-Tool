<?php

namespace App\Studio;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** Authenticator-app (TOTP) two-factor authentication with one-time recovery codes. */
class TwoFactor
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** QR code (inline SVG) to scan with an authenticator app. Rendered locally; nothing is sent to a third party. */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(setting('site.name').' Content Studio', $user->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($url);

        // Drop the XML declaration so the SVG can be placed straight into the page.
        return trim(substr($svg, strpos($svg, '<svg')));
    }

    /** Checks a 6-digit code, allowing one 30-second step of clock drift. Each code works once. */
    public function verifyCode(User $user, string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== 6 || ! $this->google2fa->verifyKey($secret, $code, 1)) {
            return false;
        }

        // Refuse a code that has already been used, so an observed code cannot be replayed.
        return Cache::add("2fa.used.{$user->id}.$code", true, now()->addMinutes(2));
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** Uses up a recovery code. Returns false when it is not one of the user's unused codes. */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
