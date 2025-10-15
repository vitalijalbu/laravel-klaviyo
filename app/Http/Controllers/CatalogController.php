<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SyncCatalogRequest;
use App\Http\Requests\Catalog\SyncSingleProductRequest;
use App\Jobs\SyncCatalogJob;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function sync(SyncCatalogRequest $request): JsonResponse
    {
        $validated = $request->validated();

        SyncCatalogJob::dispatch($validated['products']);

        return response()->json([
            'success' => true,
            'message' => 'Catalog sync queued',
            'count' => count($validated['products']),
        ]);
    }

    public function syncSingle(SyncSingleProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        SyncCatalogJob::dispatch([$validated]);

        return response()->json([
            'success' => true,
            'message' => 'Single product sync queued',
        ]);
    }
}
