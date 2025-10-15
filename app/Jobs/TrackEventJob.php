<?php

namespace App\Jobs;

use App\Actions\Klaviyo\TrackEventAction;
use App\DTO\Klaviyo\EventDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $timeout = 30;

    public function __construct(
        public EventDTO $event
    ) {
        $this->onQueue(config('klaviyo.queue.name'));
    }

    public function handle(TrackEventAction $action): void
    {
        $action->execute($this->event);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('Klaviyo event tracking failed permanently', [
            'event' => $this->event->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
