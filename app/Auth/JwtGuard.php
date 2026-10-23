<?php

namespace App\Auth;

use App\Models\User;
use App\Support\JwtService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

/**
 * Guard member berbasis Bearer JWT RS256 (PRD §5.3). Tidak memakai session.
 */
final class JwtGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        private readonly Request $request,
        UserProvider $provider,
        private readonly JwtService $jwt,
    ) {
        $this->provider = $provider;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if ($token === null || $token === '') {
            return null;
        }

        $claims = $this->jwt->parseAccessToken($token);

        if (($claims->role ?? null) !== 'member') {
            return null;
        }

        $user = $this->provider->retrieveById($claims->sub ?? null);

        if (! $user instanceof User || ! $user->is_active) {
            return null;
        }

        return $this->user = $user;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }
}
