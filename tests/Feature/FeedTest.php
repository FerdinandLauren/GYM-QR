<?php

namespace Tests\Feature;

use App\Events\FeedEvent;
use App\Models\User;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAuth;
use Tests\Support\InteractsWithGym;
use Tests\TestCase;

/**
 * 3D: feed event real-time + replay Redis Stream + otorisasi kanal (PRD §7.8).
 */
class FeedTest extends TestCase
{
    use DatabaseTruncation, InteractsWithAuth, InteractsWithGym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeOtpSender();
        $this->createGymSettings();
        Redis::del(FeedService::FEED_KEY);
    }

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_publish_menulis_stream_dan_mengirim_event_ke_kanal(): void
    {
        Event::fake([FeedEvent::class]);

        $eventId = $this->app->make(FeedService::class)->publish(
            'locker.assigned',
            ['log_id' => 10, 'locker_number' => '05'],
            7,
        );

        $this->assertNotSame('', $eventId);

        $stream = Redis::lrange(FeedService::FEED_KEY, 0, -1);
        $this->assertCount(1, $stream);

        Event::assertDispatched(FeedEvent::class, function (FeedEvent $event) use ($eventId): bool {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return $event->eventId === $eventId
                && $event->eventType === 'locker.assigned'
                && in_array('private-reception', $channels, true)
                && in_array('private-member.7', $channels, true);
        });
    }

    public function test_replay_mengembalikan_event_setelah_id_terakhir(): void
    {
        $service = $this->app->make(FeedService::class);

        $first = $service->publish('attendance.created', ['log_id' => 1], null);
        $second = $service->publish('locker.assigned', ['log_id' => 1, 'locker_number' => '05'], 7);

        $replayed = $service->replay($first);

        $this->assertCount(1, $replayed);
        $this->assertSame($second, $replayed[0]['event_id']);
        $this->assertSame('locker.assigned', $replayed[0]['type']);
    }

    public function test_staf_boleh_otorisasi_kanal_reception(): void
    {
        $staff = User::factory()->create(['role' => 'receptionist', 'phone_number' => '628111111111']);

        $this->actingAs($staff, 'staff')
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-reception',
            ])
            ->assertOk();
    }

    public function test_member_hanya_boleh_kanal_miliknya(): void
    {
        $member = $this->createMember(['phone_number' => '628123456789']);
        $other = $this->createMember(['phone_number' => '628999999999']);

        $this->actingAs($member, 'member')
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-member.'.$member->user_id,
            ])
            ->assertOk();

        $this->actingAs($member, 'member')
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-member.'.$other->user_id,
            ])
            ->assertForbidden();
    }

    public function test_checkin_mengirim_event_attendance_created(): void
    {
        Event::fake([FeedEvent::class]);

        $member = $this->createMember([
            'full_name' => 'John Doe',
            'phone_number' => '628123456789',
        ]);
        $this->createMembership($member);

        $this->postScan($member, $this->scanPayload(), (string) Str::uuid())->assertOk();

        Event::assertDispatched(FeedEvent::class, fn (FeedEvent $event): bool => $event->eventType === 'attendance.created');
    }
}
