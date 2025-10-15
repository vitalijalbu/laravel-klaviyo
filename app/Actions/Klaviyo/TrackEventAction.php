<?php

namespace App\Actions\Klaviyo;

use App\DTO\Klaviyo\EventDTO;
use App\Services\KlaviyoService;

class TrackEventAction
{
    public function __construct(
        private KlaviyoService $klaviyo
    ) {}

    public function execute(EventDTO $event): bool
    {
        return $this->klaviyo->track($event);
    }

    public function executeOnce(EventDTO $event): bool
    {
        if (! $event->uniqueId) {
            throw new \InvalidArgumentException('Event must have uniqueId for trackOnce');
        }

        return $this->klaviyo->trackOnce($event);
    }
}
