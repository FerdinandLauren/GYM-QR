<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\JwtService;
use App\Support\OtpSender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Login member lewat OTP email (FR-MEM-01, pengganti WhatsApp) dan siklus refresh token.
 */
final class MemberAuthService
{
    private const OTP_TTL_MINUTES = 5;

    private const OTP_MAX_ATTEMPTS = 5;

    private const OTP_RESEND_SECONDS = 60;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly OtpSender $sender,
    ) {}

    public function requestOtp(string $email, ?string $ipAddress): void
    {
        // Email tidak terdaftar tetap mendapat respons sukses; tidak ada OTP yang dibuat.
        if ($this->findMember($email) === null) {
            return;
        }

        $lastRequest = DB::table('otp_requests')
            ->where('email', $email)
            ->orderByDesc('otp_id')
            ->first();

        if ($lastRequest !== null && Carbon::parse($lastRequest->created_at)->addSeconds(self::OTP_RESEND_SECONDS)->isFuture()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $packedIp = $ipAddress !== null ? inet_pton($ipAddress) : false;

        DB::table('otp_requests')->insert([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'request_ip' => $packedIp === false ? null : $packedIp,
            'created_at' => now(),
        ]);

        $this->sender->send($email, $code);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string}
     */
    public function verifyOtp(string $email, string $code): array
    {
        $member = $this->findMember($email);

        if ($member === null) {
            throw ApiException::memberNotFound();
        }

        $otp = DB::table('otp_requests')
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->orderByDesc('otp_id')
            ->first();

        if ($otp === null) {
            throw ApiException::otpInvalid();
        }

        if (Carbon::parse($otp->expires_at)->isPast()) {
            throw ApiException::otpExpired();
        }

        if ((int) $otp->attempts >= self::OTP_MAX_ATTEMPTS) {
            throw ApiException::otpInvalid();
        }

        if (! Hash::check($code, $otp->code_hash)) {
            DB::table('otp_requests')->where('otp_id', $otp->otp_id)->increment('attempts');

            throw ApiException::otpInvalid();
        }

        DB::table('otp_requests')->where('otp_id', $otp->otp_id)->update(['consumed_at' => now()]);

        return $this->issueTokens($member);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string}
     */
    public function refresh(string $refreshToken): array
    {
        $row = DB::table('refresh_tokens')
            ->where('token_hash', hash('sha256', $refreshToken))
            ->first();

        if ($row === null || $row->revoked_at !== null || Carbon::parse($row->expires_at)->isPast()) {
            throw ApiException::unauthenticated();
        }

        // Rotasi atomik: hanya satu request yang boleh memakai token ini.
        $revoked = DB::table('refresh_tokens')
            ->where('token_id', $row->token_id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($revoked === 0) {
            throw ApiException::unauthenticated();
        }

        $member = User::query()
            ->whereKey($row->user_id)
            ->where('role', 'member')
            ->where('is_active', true)
            ->first();

        if ($member === null) {
            throw ApiException::unauthenticated();
        }

        return $this->issueTokens($member);
    }

    public function logout(User $member): void
    {
        DB::table('refresh_tokens')
            ->where('user_id', $member->user_id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string}
     */
    private function issueTokens(User $member): array
    {
        $refreshToken = Str::random(64);

        DB::table('refresh_tokens')->insert([
            'user_id' => $member->user_id,
            'token_hash' => hash('sha256', $refreshToken),
            'expires_at' => now()->addSeconds((int) config('auth.jwt.refresh_ttl')),
            'created_at' => now(),
        ]);

        return [
            'access_token' => $this->jwt->issueAccessToken($member),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->accessTtl(),
            'refresh_token' => $refreshToken,
        ];
    }

    private function findMember(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->where('role', 'member')
            ->where('is_active', true)
            ->first();
    }
}
