<?php

namespace App\Http\Controllers;

use App\DTO\Klaviyo\CustomerDTO;
use App\DTO\Klaviyo\EventDTO;
use App\DTO\Klaviyo\OrderDTO;
use App\DTO\Klaviyo\ProductDTO;
use App\Http\Requests\Event\OrderPlacedRequest;
use App\Http\Requests\Event\ProductViewRequest;
use App\Http\Requests\Event\TrackEventRequest;
use App\Jobs\IdentifyCustomerJob;
use App\Jobs\TrackEventJob;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function track(TrackEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $event = EventDTO::create(
            $validated['event'],
            $validated['properties'],
            $validated['customer'] ?? null
        );

        TrackEventJob::dispatch($event);

        return response()->json([
            'success' => true,
            'message' => 'Event queued for processing',
        ]);
    }

    public function productView(ProductViewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = ProductDTO::fromSylius($validated);

        $event = EventDTO::create(
            'Viewed Product',
            $product->toEventProperties(),
            $validated['customer'] ?? null
        );

        TrackEventJob::dispatch($event);

        return response()->json([
            'success' => true,
            'message' => 'Product view event queued',
        ]);
    }

    public function orderPlaced(OrderPlacedRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Identify customer first
        $customer = CustomerDTO::fromArray($validated['customer']);
        IdentifyCustomerJob::dispatch($customer);

        // Track order
        $order = OrderDTO::fromSylius($validated);
        $event = EventDTO::create(
            'Placed Order',
            $order->toEventProperties(),
            $validated['customer']
        );

        TrackEventJob::dispatch($event);

        return response()->json([
            'success' => true,
            'message' => 'Order placed event queued',
        ]);
    }
}
