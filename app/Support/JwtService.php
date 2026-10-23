<?php

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\User;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/**
 * Access token member: JWT RS256, 15 menit (PRD §5.3).
 */
final class JwtService
{
    public function issueAccessToken(User $user): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $user->user_id,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + $this->accessTtl(),
        ], $this->key('auth.jwt.private_key_path', 'private'), 'RS256');
    }

    public function parseAccessToken(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->key('auth.jwt.public_key_path', 'public'), 'RS256'));
        } catch (ExpiredException) {
            throw ApiException::tokenExpired();
        } catch (Throwable) {
            throw ApiException::unauthenticated();
        }
    }

    public function accessTtl(): int
    {
        return (int) config('auth.jwt.access_ttl');
    }

    private function key(string $configKey, string $label): string
    {
        $path = (string) config($configKey);

        if (! is_file($path)) {
            throw new RuntimeException("Kunci JWT {$label} tidak ditemukan di {$path}.");
        }

        return (string) file_get_contents($path);
    }
}
