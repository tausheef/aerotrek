<?php

namespace App\Services\Email;

use App\Models\EmailOtp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private const TTL_MINUTES        = 10;
    private const RESEND_COOLDOWN_S  = 60;

    public function sendOtp(string $email, string $name): void
    {
        EmailOtp::where('email', $email)->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailOtp::create([
            'email'      => $email,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->dispatch($email, $name, $otp);
    }

    /**
     * @return array{sent: bool, wait?: int}
     */
    public function resendOtp(string $email, string $name): array
    {
        $latest = EmailOtp::where('email', $email)
            ->orderByDesc('created_at')
            ->first();

        if ($latest) {
            $elapsed = (int) $latest->created_at->diffInSeconds(now());
            if ($elapsed < self::RESEND_COOLDOWN_S) {
                return ['sent' => false, 'wait' => self::RESEND_COOLDOWN_S - $elapsed];
            }
        }

        $this->sendOtp($email, $name);
        return ['sent' => true];
    }

    public function verify(string $email, string $otp): bool
    {
        $record = EmailOtp::where('email', $email)
            ->where('otp', $otp)
            ->whereNull('used_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$record || $record->isExpired()) {
            return false;
        }

        $record->update(['used_at' => now()]);
        return true;
    }

    private function dispatch(string $email, string $name, string $otp): void
    {
        $response = Http::withToken(config('services.resend.key'))
            ->post('https://api.resend.com/emails', [
                'from'    => 'AeroTrek Courier <noreply@aerotrekcourier.com>',
                'to'      => [$email],
                'subject' => "Your AeroTrek verification code: {$otp}",
                'html'    => $this->buildHtml($name, $otp),
            ]);

        if (!$response->successful()) {
            Log::error('Resend OTP failed', [
                'email'  => $email,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Failed to send verification email. Please try again.');
        }
    }

    private function buildHtml(string $name, string $otp): string
    {
        $firstName = explode(' ', trim($name))[0];

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 20px">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);max-width:100%">
        <tr>
          <td style="background:#0D2C54;padding:28px 40px;text-align:center">
            <span style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-0.5px">
              AEROTREK <span style="color:#F97316">COURIER</span>
            </span>
          </td>
        </tr>
        <tr>
          <td style="padding:40px">
            <p style="margin:0 0 6px;font-size:26px;font-weight:700;color:#0D2C54;line-height:1.2">Verify your email</p>
            <p style="margin:0 0 32px;font-size:15px;color:#555;line-height:1.6">
              Hi {$firstName}, use the code below to complete your AeroTrek account setup.
            </p>
            <div style="background:#f8f8f8;border:2px dashed #e2e8f0;border-radius:12px;padding:28px;text-align:center;margin-bottom:28px">
              <span style="font-size:46px;font-weight:800;letter-spacing:14px;color:#0D2C54;font-family:'Courier New',monospace">{$otp}</span>
            </div>
            <p style="margin:0 0 6px;font-size:13px;color:#999;text-align:center">
              This code expires in <strong>10 minutes</strong>.
            </p>
            <p style="margin:0;font-size:13px;color:#999;text-align:center">
              Never share this code with anyone.
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#fafafa;padding:20px 40px;border-top:1px solid #f0f0f0">
            <p style="margin:0;font-size:12px;color:#bbb;text-align:center">
              © 2026 AeroTrek Courier &nbsp;·&nbsp; If you didn't create an account, you can safely ignore this email.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
