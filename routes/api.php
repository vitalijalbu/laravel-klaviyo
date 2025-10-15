<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'klaviyo-integration',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::middleware('api.key')->group(function () {
    // Events
    Route::post('/events/track', [EventController::class, 'track']);
    Route::post('/events/product-view', [EventController::class, 'productView']);
    Route::post('/events/order-placed', [EventController::class, 'orderPlaced']);

    // Catalog
    Route::post('/catalog/sync', [CatalogController::class, 'sync']);
    Route::post('/catalog/sync-single', [CatalogController::class, 'syncSingle']);
});
