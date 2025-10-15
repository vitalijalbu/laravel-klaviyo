<?php

namespace App\Jobs;

use App\Actions\Klaviyo\IdentifyCustomerAction;
use App\DTO\Klaviyo\CustomerDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IdentifyCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public function __construct(
        public CustomerDTO $customer
    ) {
        $this->onQueue(config('klaviyo.queue.name'));
    }

    public function handle(IdentifyCustomerAction $action): void
    {
        $action->execute($this->customer);
    }
}
