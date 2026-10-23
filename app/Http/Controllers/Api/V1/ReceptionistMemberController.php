<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MembershipResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FR-CAS-08: pencarian member untuk check-in manual dan penjualan paket.
 */
final class ReceptionistMemberController extends Controller
{
    public function index(Request $request, MembershipResolver $memberships): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3'],
        ]);

        $term = '%'.$validated['q'].'%';

        $users = DB::table('users')
            ->where('role', 'member')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($term): void {
                $query->where('full_name', 'like', $term)
                    ->orWhere('phone_number', 'like', $term);
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get(['user_id', 'full_name', 'phone_number', 'photo_url']);

        $now = now();
        $members = [];

        foreach ($users as $user) {
            $memberMemberships = DB::table('memberships')
                ->where('user_id', $user->user_id)
                ->orderByDesc('membership_id')
                ->get();

            $members[] = [
                'user_id' => (int) $user->user_id,
                'full_name' => (string) $user->full_name,
                'phone_masked' => $this->maskPhone((string) $user->phone_number),
                'photo_url' => $user->photo_url,
                'memberships' => $memberMemberships->map(fn (object $membership): array => [
                    'membership_id' => (int) $membership->membership_id,
                    'type' => (string) $membership->type,
                    'effective_status' => $memberships->effectiveStatus($membership, $now),
                    'end_date' => $membership->end_date,
                    'remaining_visits' => $membership->remaining_visits === null ? null : (int) $membership->remaining_visits,
                ])->all(),
            ];
        }

        return ApiResponse::success('Daftar member dimuat.', ['members' => $members]);
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 8) {
            return $phone;
        }

        return substr($phone, 0, 4).'****'.substr($phone, -4);
    }
}
