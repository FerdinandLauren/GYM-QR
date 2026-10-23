<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Envelope event feed PRD §7.8.
 */
final class FeedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $occurredAt;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly array $payload,
        public readonly ?int $memberId = null,
    ) {
        $this->occurredAt = Carbon::now('UTC')->toIso8601ZuluString();
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('reception')];

        if ($this->memberId !== null) {
            $channels[] = new PrivateChannel('member.'.$this->memberId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'type' => $this->eventType,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
        ];
    }
}
