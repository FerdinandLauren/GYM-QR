<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Kolom mengikuti PRD §6.1 tabel users: tanpa email, password, atau remember_token.
 *
 * @property int $user_id
 * @property string $full_name
 * @property string $phone_number
 * @property string|null $photo_url
 * @property string $role
 * @property string|null $password_hash
 * @property string|null $totp_secret
 * @property bool $is_active
 * @property Carbon|null $consent_at
 * @property string|null $consent_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $primaryKey = 'user_id';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'phone_number',
        'photo_url',
        'role',
        'password_hash',
        'totp_secret',
        'is_active',
        'consent_at',
        'consent_version',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'totp_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'totp_secret' => 'encrypted',
            'is_active' => 'boolean',
            'consent_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }
}
