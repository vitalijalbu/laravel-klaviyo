<?php

namespace Tests\Feature;

use App\DTO\Klaviyo\EventDTO;
use App\Jobs\IdentifyCustomerJob;
use App\Jobs\TrackEventJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.api_key' => 'test-key']);
    }

    public function test_product_view_creates_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events/product-view', [
            'product_id' => 123,
            'product_name' => 'Test Product',
            'price' => 29.99,
            'currency' => 'EUR',
            'customer' => [
                'email' => 'test@example.com',
            ],
        ], [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Queue::assertPushed(TrackEventJob::class, function ($job) {
            return $job->event instanceof EventDTO &&
                   $job->event->event === 'Viewed Product';
        });
    }

    public function test_order_placed_creates_jobs(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events/order-placed', [
            'order_id' => 123,
            'order_number' => 'ORD-123',
            'total' => 99.99,
            'currency' => 'EUR',
            'items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'price' => 99.99,
                ],
            ],
            'customer' => [
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
        ], [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Should create both identify and track jobs
        Queue::assertPushed(IdentifyCustomerJob::class);
        Queue::assertPushed(TrackEventJob::class, function ($job) {
            return $job->event instanceof EventDTO &&
                   $job->event->event === 'Placed Order';
        });
    }

    public function test_generic_track_event(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events/track', [
            'event' => 'Custom Event',
            'properties' => [
                'custom_prop' => 'value',
                'number_prop' => 42,
            ],
            'customer' => [
                'email' => 'test@example.com',
            ],
        ], [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Queue::assertPushed(TrackEventJob::class, function ($job) {
            return $job->event instanceof EventDTO &&
                   $job->event->event === 'Custom Event';
        });
    }

    public function test_unauthorized_without_api_key(): void
    {
        $response = $this->postJson('/api/events/track', [
            'event' => 'Test Event',
            'properties' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_validation_fails_on_invalid_data(): void
    {
        $response = $this->postJson('/api/events/product-view', [
            'product_id' => 123,
            // Missing required fields
        ], [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertStatus(422);
    }

    public function test_catalog_sync(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/catalog/sync', [
            'products' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Test Product 1',
                    'price' => 29.99,
                    'currency' => 'EUR',
                ],
                [
                    'product_id' => 2,
                    'product_name' => 'Test Product 2',
                    'price' => 39.99,
                    'currency' => 'EUR',
                ],
            ],
        ], [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Catalog sync queued',
                'count' => 2,
            ]);

        Queue::assertPushed(\App\Jobs\SyncCatalogJob::class);
    }
}
