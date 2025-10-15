<?php

namespace App\Jobs;

use App\Actions\Klaviyo\SyncCatalogAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 120; // Più lungo per bulk

    public function __construct(
        public array $products
    ) {
        $this->onQueue(config('klaviyo.queue.name'));
    }

    public function handle(SyncCatalogAction $action): void
    {
        $results = $action->syncBulk($this->products);

        Log::info('Catalog sync completed', $results);
    }
}
