<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Administrasi paket, loker, gym settings, staf, dan tampilan audit (FR-ADM).
 */
final class AdminService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function packages(): array
    {
        return DB::table('packages')->orderBy('package_id')->get()->map(fn (object $row): array => [
            'package_id' => (int) $row->package_id,
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'type' => (string) $row->type,
            'duration_days' => (int) $row->duration_days,
            'visit_quota' => $row->visit_quota === null ? null : (int) $row->visit_quota,
            'price' => (float) $row->price,
            'is_active' => (bool) $row->is_active,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPackage(User $admin, array $data): array
    {
        $exists = DB::table('packages')->where('code', (string) $data['code'])->exists();

        if ($exists) {
            throw ApiException::validationError('Kode paket sudah dipakai.');
        }

        $id = DB::table('packages')->insertGetId([
            'code' => (string) $data['code'],
            'name' => (string) $data['name'],
            'type' => (string) $data['type'],
            'duration_days' => (int) $data['duration_days'],
            'visit_quota' => $data['visit_quota'] ?? null,
            'price' => (float) $data['price'],
            'is_active' => 1,
        ]);

        $this->audit->log($admin, 'package.created', 'package', $id, null, ['code' => (string) $data['code']]);

        return $this->packageById($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePackage(User $admin, int $packageId, array $data): array
    {
        $package = DB::table('packages')->where('package_id', $packageId)->first();

        if ($package === null) {
            throw ApiException::notFound('Paket tidak ditemukan.');
        }

        $changes = array_filter([
            'name' => $data['name'] ?? null,
            'duration_days' => $data['duration_days'] ?? null,
            'visit_quota' => $data['visit_quota'] ?? null,
            'price' => $data['price'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (int) (bool) $data['is_active'] : null,
        ], fn (mixed $value): bool => $value !== null);

        DB::table('packages')->where('package_id', $packageId)->update($changes);
        $this->audit->log($admin, 'package.updated', 'package', $packageId, null, $changes);

        return $this->packageById($packageId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lockers(): array
    {
        return DB::table('lockers')->orderBy('locker_number')->get()->map(fn (object $row): array => [
            'locker_id' => (int) $row->locker_id,
            'locker_number' => (string) $row->locker_number,
            'status' => (string) $row->status,
            'note' => $row->note,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createLocker(User $admin, array $data): array
    {
        $exists = DB::table('lockers')->where('locker_number', (string) $data['locker_number'])->exists();

        if ($exists) {
            throw ApiException::validationError('Nomor loker sudah dipakai.');
        }

        $id = DB::table('lockers')->insertGetId([
            'locker_number' => (string) $data['locker_number'],
            'status' => 'available',
        ]);

        $this->audit->log($admin, 'locker.created', 'locker', $id, null, ['locker_number' => (string) $data['locker_number']]);

        return $this->lockerById($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateLocker(User $admin, int $lockerId, array $data): array
    {
        $locker = DB::table('lockers')->where('locker_id', $lockerId)->first();

        if ($locker === null) {
            throw ApiException::notFound('Loker tidak ditemukan.');
        }

        $status = $data['status'] ?? null;

        if ($status === 'out_of_service' && empty($data['reason'])) {
            throw ApiException::validationError('Alasan wajib diisi saat menonaktifkan loker.');
        }

        $changes = array_filter([
            'locker_number' => $data['locker_number'] ?? null,
            'status' => $status,
            'note' => $data['reason'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        DB::table('lockers')->where('locker_id', $lockerId)->update($changes);

        $action = $status === 'out_of_service' ? 'locker.out_of_service'
            : ($locker->status === 'out_of_service' && $status === 'available' ? 'locker.restored' : 'locker.updated');

        $this->audit->log($admin, $action, 'locker', $lockerId, ['status' => (string) $locker->status], $changes);

        return $this->lockerById($lockerId);
    }

    /**
     * @return array<string, mixed>
     */
    public function gymSettings(): array
    {
        $row = DB::table('gym_settings')->first();

        if ($row === null) {
            throw ApiException::serviceUnavailable();
        }

        return [
            'gym_name' => (string) $row->gym_name,
            'timezone' => (string) $row->timezone,
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'geofence_radius_m' => (int) $row->geofence_radius_m,
            'geofence_accuracy_max_m' => (int) $row->geofence_accuracy_max_m,
            'closing_time' => (string) $row->closing_time,
            'qr_payload' => (string) $row->qr_payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateGymSettings(User $admin, array $data): array
    {
        $changes = array_filter([
            'gym_name' => $data['gym_name'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'geofence_radius_m' => $data['geofence_radius_m'] ?? null,
            'geofence_accuracy_max_m' => $data['geofence_accuracy_max_m'] ?? null,
            'closing_time' => $data['closing_time'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        DB::table('gym_settings')->update($changes);
        $this->audit->log($admin, 'gym.settings_updated', 'gym_settings', 1, null, $changes);

        return $this->gymSettings();
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerateQr(User $admin): array
    {
        $payload = 'GYM_STATIC_'.Str::upper(Str::random(24));

        DB::table('gym_settings')->update(['qr_payload' => $payload]);
        $this->audit->log($admin, 'gym.qr_regenerated', 'gym_settings', 1);

        return ['qr_payload' => $payload];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function staff(): array
    {
        return DB::table('users')
            ->whereIn('role', ['receptionist', 'admin'])
            ->whereNull('deleted_at')
            ->orderBy('user_id')
            ->get()
            ->map(fn (object $row): array => [
                'user_id' => (int) $row->user_id,
                'full_name' => (string) $row->full_name,
                'phone_number' => (string) $row->phone_number,
                'role' => (string) $row->role,
                'is_active' => (bool) $row->is_active,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createStaff(User $admin, array $data): array
    {
        $phone = PhoneNumber::normalize((string) $data['phone_number']);

        if (DB::table('users')->where('phone_number', $phone)->exists()) {
            throw ApiException::validationError('Nomor sudah terdaftar.');
        }

        $id = DB::table('users')->insertGetId([
            'full_name' => (string) $data['full_name'],
            'phone_number' => $phone,
            'role' => (string) $data['role'],
            'password_hash' => Hash::make((string) $data['password']),
            'totp_secret' => $data['totp_secret'] ?? null,
            'is_active' => 1,
        ]);

        $this->audit->log($admin, 'staff.created', 'user', $id, null, ['role' => (string) $data['role']]);

        return $this->staffById($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateStaff(User $admin, int $userId, array $data): array
    {
        $staff = DB::table('users')->where('user_id', $userId)->whereIn('role', ['receptionist', 'admin'])->first();

        if ($staff === null) {
            throw ApiException::notFound('Staf tidak ditemukan.');
        }

        $changes = array_filter([
            'full_name' => $data['full_name'] ?? null,
            'role' => $data['role'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (int) (bool) $data['is_active'] : null,
            'password_hash' => isset($data['password']) ? Hash::make((string) $data['password']) : null,
        ], fn (mixed $value): bool => $value !== null);

        DB::table('users')->where('user_id', $userId)->update($changes);
        $this->audit->log($admin, 'staff.updated', 'user', $userId, null, ['fields' => array_keys($changes)]);

        return $this->staffById($userId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function auditLogs(array $filters): array
    {
        $query = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.user_id', '=', 'a.actor_user_id')
            ->orderByDesc('a.audit_id')
            ->limit(100)
            ->select('a.*', 'u.full_name');

        if (isset($filters['actor_user_id'])) {
            $query->where('a.actor_user_id', (int) $filters['actor_user_id']);
        }

        if (isset($filters['action'])) {
            $query->where('a.action', (string) $filters['action']);
        }

        if (isset($filters['from'])) {
            $query->where('a.created_at', '>=', Carbon::parse((string) $filters['from'], 'Asia/Jakarta')->startOfDay()->utc());
        }

        if (isset($filters['to'])) {
            $query->where('a.created_at', '<=', Carbon::parse((string) $filters['to'], 'Asia/Jakarta')->endOfDay()->utc());
        }

        return $query->get()->map(fn (object $row): array => [
            'audit_id' => (int) $row->audit_id,
            'actor_user_id' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
            'actor_name' => $row->full_name,
            'actor_role' => $row->actor_role,
            'action' => (string) $row->action,
            'entity_type' => $row->entity_type,
            'entity_id' => $row->entity_id === null ? null : (int) $row->entity_id,
            'before' => $row->before_json === null ? null : json_decode((string) $row->before_json, true),
            'after' => $row->after_json === null ? null : json_decode((string) $row->after_json, true),
            'created_at' => Carbon::parse((string) $row->created_at, 'UTC')->toIso8601ZuluString(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function packageById(int $id): array
    {
        $row = DB::table('packages')->where('package_id', $id)->first();

        return [
            'package_id' => (int) $row->package_id,
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'type' => (string) $row->type,
            'duration_days' => (int) $row->duration_days,
            'visit_quota' => $row->visit_quota === null ? null : (int) $row->visit_quota,
            'price' => (float) $row->price,
            'is_active' => (bool) $row->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lockerById(int $id): array
    {
        $row = DB::table('lockers')->where('locker_id', $id)->first();

        return [
            'locker_id' => (int) $row->locker_id,
            'locker_number' => (string) $row->locker_number,
            'status' => (string) $row->status,
            'note' => $row->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffById(int $id): array
    {
        $row = DB::table('users')->where('user_id', $id)->first();

        return [
            'user_id' => (int) $row->user_id,
            'full_name' => (string) $row->full_name,
            'phone_number' => (string) $row->phone_number,
            'role' => (string) $row->role,
            'is_active' => (bool) $row->is_active,
        ];
    }
}
