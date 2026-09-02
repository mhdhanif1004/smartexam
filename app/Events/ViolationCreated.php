<?php

namespace App\Events;

use App\Models\Violation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViolationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Violation $violation,
        public readonly int $roomId,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('violations.room.' . $this->roomId),
            // channel admin global — semua admin juga dapat realtime
            new PrivateChannel('violations.admin'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'violation' => $this->payload,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ViolationCreated';
    }
}
