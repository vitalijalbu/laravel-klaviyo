<?php

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'klaviyo-integration',
        'queue_size' => Queue::size('klaviyo'),
        'timestamp' => now()->toIso8601String(),
    ]);
});
Route::get('logs', [LogViewerController::class, 'index']);