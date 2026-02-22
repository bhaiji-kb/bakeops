<?php

namespace App\Jobs;

use App\Models\ChannelOrderEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessChannelOrderEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $eventId
    ) {
    }

    public function handle(): void
    {
        $event = ChannelOrderEvent::query()->find($this->eventId);
        if (!$event) {
            return;
        }

        if ($event->processed_at) {
            return;
        }

        $event->update([
            'process_status' => 'processed',
            'processed_at' => now(),
            'process_error' => null,
        ]);
    }
}
