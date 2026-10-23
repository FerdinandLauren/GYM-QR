<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan, perpanjangan, dan aksi admin pada membership (PRD §4.3).
 * Setiap penjualan/perpanjangan membuat baris membership baru agar riwayat utuh.
 */
final class MembershipService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sell(User $staff, array $data): array
    {
        return DB::transaction(fn (): array => $this->persistSale($staff, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persistSale(User $staff, array $data): array
    {
        $package = DB::table('packages')
            ->where('package_id', (int) $data['package_id'])
            ->where('is_active', 1)
            ->first();

        if ($package === null) {
            throw ApiException::validationError('Paket tidak ditemukan atau tidak aktif.');
        }

        $phone = PhoneNumber::normalize((string) $data['phone_number']);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $action = (string) $data['action'];

        if ($action === 'new') {
            $user = DB::table('users')->where('phone_number', $phone)->first();

            if ($user !== null) {
                throw ApiException::validationError('Nomor sudah terdaftar. Gunakan perpanjangan.');
            }

            $userId = DB::table('users')->insertGetId([
                'full_name' => (string) $data['full_name'],
                'phone_number' => $phone,
                'photo_url' => (string) $data['photo_url'],
                'role' => 'member',
                'is_active' => 1,
                'consent_at' => now(),
                'consent_version' => '1.2',
            ]);

            $member = DB::table('users')->where('user_id', $userId)->first();

            if ($member === null) {
                throw ApiException::notFound('Member tidak ditemukan setelah dibuat.');
            }

            $startDate = $today;
        } else {
            $member = DB::table('users')
                ->where('phone_number', $phone)
                ->where('role', 'member')
                ->whereNull('deleted_at')
                ->first();

            if ($member === null) {
                throw ApiException::memberNotFound();
            }

            $latest = DB::table('memberships')
                ->where('user_id', $member->user_id)
                ->orderByDesc('end_date')
                ->orderByDesc('membership_id')
                ->first();

            if ($latest === null) {
                throw ApiException::validationError('Member belum memiliki paket. Pilih aksi new.');
            }

            $startDate = $latest->end_date !== null && $latest->end_date > $today
                ? (string) $latest->end_date
                : $today;
        }

        $isSingle = $package->type === 'single_visit';
        $endDate = $isSingle
            ? $today
            : Carbon::parse($startDate)->addDays((int) $package->duration_days - 1)->toDateString();

        $membershipId = DB::table('memberships')->insertGetId([
            'user_id' => (int) $member->user_id,
            'package_id' => (int) $package->package_id,
            'type' => (string) $package->type,
            'remaining_visits' => $isSingle ? (int) ($package->visit_quota ?? 1) : null,
            'price_snapshot' => (float) $package->price,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
            'created_by_staff_id' => $staff->user_id,
            'sold_by_staff_id' => $staff->user_id,
        ]);

        $this->audit->log(
            $staff,
            $action === 'new' ? 'membership.created' : 'membership.renewed',
            'membership',
            $membershipId,
            null,
            ['user_id' => (int) $member->user_id, 'package_id' => (int) $package->package_id],
        );

        return [
            'user' => [
                'user_id' => (int) $member->user_id,
                'full_name' => (string) $member->full_name,
                'phone_number' => (string) $member->phone_number,
                'photo_url' => $member->photo_url,
            ],
            'membership' => [
                'membership_id' => $membershipId,
                'package_name' => (string) $package->name,
                'type' => (string) $package->type,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'remaining_visits' => $isSingle ? (int) ($package->visit_quota ?? 1) : null,
                'status' => 'active',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function adminAction(User $admin, int $membershipId, array $data): array
    {
        return DB::transaction(function () use ($admin, $membershipId, $data): array {
            $membership = DB::table('memberships')->where('membership_id', $membershipId)->lockForUpdate()->first();

            if ($membership === null) {
                throw ApiException::notFound('Membership tidak ditemukan.');
            }

            $today = Carbon::now('Asia/Jakarta')->toDateString();
            $action = (string) $data['action'];

            switch ($action) {
                case 'suspend':
                    $status = 'suspended';
                    $audit = 'membership.suspended';
                    break;
                case 'cancel':
                    $status = 'cancelled';
                    $audit = 'membership.cancelled';
                    break;
                case 'reactivate':
                    $expired = $membership->end_date !== null && $membership->end_date < $today;
                    $exhausted = $membership->type === 'single_visit' && (int) $membership->remaining_visits <= 0;

                    if ($expired || $exhausted) {
                        throw ApiException::validationError('Paket sudah kedaluwarsa. Perpanjang dulu.');
                    }

                    $status = 'active';
                    $audit = 'membership.reactivated';
                    break;
                case 'extend':
                    $days = (int) ($data['days'] ?? 0);

                    if ($days < 1 || $days > 365) {
                        throw ApiException::validationError('Jumlah hari perpanjangan tidak valid.');
                    }

                    $basis = $membership->end_date !== null && $membership->end_date > $today
                        ? (string) $membership->end_date
                        : $today;

                    $endDate = Carbon::parse($basis)->addDays($days - 1)->toDateString();
                    $status = 'active';
                    $audit = 'membership.extended';

                    DB::table('memberships')->where('membership_id', $membershipId)->update([
                        'end_date' => $endDate,
                        'status' => $status,
                    ]);
                    break;
                default:
                    throw ApiException::validationError('Aksi membership tidak dikenal.');
            }

            if ($action !== 'extend') {
                DB::table('memberships')->where('membership_id', $membershipId)->update(['status' => $status]);
            }

            $this->audit->log($admin, $audit, 'membership', $membershipId, null, [
                'reason' => $data['reason'] ?? null,
                'status' => $status,
            ]);

            return [
                'membership_id' => $membershipId,
                'status' => $status,
                'end_date' => DB::table('memberships')->where('membership_id', $membershipId)->value('end_date'),
            ];
        });
    }
}
