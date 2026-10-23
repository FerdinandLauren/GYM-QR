<?php

namespace App\Services;

use App\Events\FeedEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

/**
 * Feed kasir: simpan event di Redis list (retensi terbatas) lalu broadcast ke
 * Reverb. Redis mati tidak boleh menggagalkan aksi bisnis (PRD §8.7).
 *
 * shortcut: retensi berbasis jumlah item (10000), bukan 6 jam; upgrade ke
 * Redis Stream saat Redis server mendukung perintah stream lewat klien.
 */
final class FeedService
{
    public const FEED_KEY = 'gym:feed';

    private const MAX_EVENTS = 10000;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $type, array $payload, ?int $memberId = null): string
    {
        $eventId = $this->append($type, $payload);

        try {
            broadcast(new FeedEvent($eventId, $type, $payload, $memberId));
        } catch (Throwable $exception) {
            Log::warning('Broadcast feed gagal; klien memakai polling.', [
                'exception' => $exception->getMessage(),
                'type' => $type,
            ]);
        }

        return $eventId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function replay(string $afterEventId): array
    {
        if ($afterEventId === '') {
            return [];
        }

        try {
            $items = Redis::lrange(self::FEED_KEY, 0, -1);
        } catch (Throwable $exception) {
            Log::warning('Replay feed gagal; klien memuat ulang daftar sesi.', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }

        $events = [];
        $seen = false;

        foreach ($items as $item) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $item, true);

            if (! $seen) {
                $seen = $decoded['event_id'] === $afterEventId;

                continue;
            }

            $events[] = $decoded;
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function append(string $type, array $payload): string
    {
        $eventId = (string) Str::ulid();
        $event = json_encode([
            'event_id' => $eventId,
            'type' => $type,
            'occurred_at' => Carbon::now('UTC')->toIso8601ZuluString(),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            Redis::rpush(self::FEED_KEY, [$event]);
            Redis::ltrim(self::FEED_KEY, -self::MAX_EVENTS, -1);
        } catch (Throwable $exception) {
            // Tanpa Redis, event tetap dikirim; replay jatuh ke muat ulang penuh.
            Log::warning('Penyimpanan feed Redis tidak tersedia.', ['exception' => $exception->getMessage()]);
        }

        return $eventId;
    }
}
