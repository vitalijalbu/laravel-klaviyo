<?php

namespace App\Services;

use App\DTO\Klaviyo\CustomerDTO;
use App\DTO\Klaviyo\EventDTO;
use App\DTO\Klaviyo\ProductDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KlaviyoService
{
    private string $apiKey;

    private string $apiUrl;

    private string $apiVersion;

    public function __construct()
    {
        $this->apiKey = config('klaviyo.api_key');
        $this->apiUrl = config('klaviyo.api_url');
        $this->apiVersion = config('klaviyo.api_version');
    }

    // ============================================
    // EVENT TRACKING
    // ============================================

    public function track(EventDTO $event): bool
    {
        $response = $this->sendRequest(
            'POST',
            '/events/',
            $event->toKlaviyoPayload()
        );

        if ($response->successful()) {
            Log::info('Klaviyo event tracked', [
                'event' => $event->event,
                'has_customer' => $event->customer !== null,
            ]);

            return true;
        }

        $this->handleError($response, 'track', $event->event);

        return false;
    }

    public function trackOnce(EventDTO $event): bool
    {
        // Track once usa unique_id per evitare duplicati
        if (! $event->uniqueId) {
            throw new \InvalidArgumentException('trackOnce requires uniqueId');
        }

        return $this->track($event);
    }

    // ============================================
    // CUSTOMER IDENTIFICATION
    // ============================================

    public function identify(CustomerDTO $customer): bool
    {
        $payload = [
            'data' => [
                'type' => 'profile',
                'attributes' => $customer->toKlaviyoFormat(),
            ],
        ];

        $response = $this->sendRequest('POST', '/profiles/', $payload);

        if ($response->successful()) {
            Log::info('Klaviyo customer identified', [
                'email' => $customer->email,
            ]);

            return true;
        }

        $this->handleError($response, 'identify', $customer->email);

        return false;
    }

    public function updateProfile(string $profileId, array $attributes): bool
    {
        $payload = [
            'data' => [
                'type' => 'profile',
                'id' => $profileId,
                'attributes' => $attributes,
            ],
        ];

        $response = $this->sendRequest('PATCH', "/profiles/{$profileId}/", $payload);

        return $response->successful();
    }

    // ============================================
    // PROFILE DELETION (GDPR)
    // ============================================

    public function deleteProfile(string $email): bool
    {
        // Prima trova il profile ID
        $profileId = $this->getProfileIdByEmail($email);

        if (! $profileId) {
            Log::warning('Profile not found for deletion', ['email' => $email]);

            return false;
        }

        $response = $this->sendRequest(
            'DELETE',
            '/data-privacy-deletion-jobs/',
            [
                'data' => [
                    'type' => 'data-privacy-deletion-job',
                    'attributes' => [
                        'profile' => ['data' => ['type' => 'profile', 'id' => $profileId]],
                    ],
                ],
            ]
        );

        if ($response->successful()) {
            Log::info('Klaviyo profile deletion requested', [
                'email' => $email,
                'profile_id' => $profileId,
            ]);

            return true;
        }

        return false;
    }

    private function getProfileIdByEmail(string $email): ?string
    {
        $response = $this->sendRequest(
            'GET',
            '/profiles/',
            [],
            ['filter' => "equals(email,\"{$email}\")"]
        );

        if ($response->successful()) {
            $data = $response->json();

            return $data['data'][0]['id'] ?? null;
        }

        return null;
    }

    // ============================================
    // CATALOG MANAGEMENT
    // ============================================

    public function upsertCatalogItem(ProductDTO $product): bool
    {
        $payload = [
            'data' => $product->toCatalogItem(),
        ];

        $response = $this->sendRequest(
            'POST',
            '/catalog-items/',
            $payload
        );

        if ($response->successful() || $response->status() === 409) {
            // 409 = già exists, fai update
            if ($response->status() === 409) {
                return $this->updateCatalogItem($product);
            }

            return true;
        }

        $this->handleError($response, 'upsertCatalogItem', (string) $product->id);

        return false;
    }

    public function updateCatalogItem(ProductDTO $product): bool
    {
        $catalogId = "\$custom:::\$default:::{$product->id}";

        $payload = [
            'data' => [
                'type' => 'catalog-item',
                'id' => $catalogId,
                'attributes' => $product->toCatalogItem()['attributes'],
            ],
        ];

        $response = $this->sendRequest(
            'PATCH',
            "/catalog-items/{$catalogId}/",
            $payload
        );

        return $response->successful();
    }

    public function deleteCatalogItem(string $productId): bool
    {
        $catalogId = "\$custom:::\$default:::{$productId}";

        $response = $this->sendRequest(
            'DELETE',
            "/catalog-items/{$catalogId}/"
        );

        return $response->successful();
    }

    public function bulkUpsertCatalog(array $products): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($products as $productData) {
            try {
                $product = ProductDTO::fromSylius($productData);

                if ($this->upsertCatalogItem($product)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'product_id' => $productData['product_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    // ============================================
    // LISTS & SEGMENTS
    // ============================================

    public function addToList(string $listId, string $email): bool
    {
        $profileId = $this->getProfileIdByEmail($email);

        if (! $profileId) {
            return false;
        }

        $payload = [
            'data' => [
                [
                    'type' => 'profile',
                    'id' => $profileId,
                ],
            ],
        ];

        $response = $this->sendRequest(
            'POST',
            "/lists/{$listId}/relationships/profiles/",
            $payload
        );

        return $response->successful();
    }

    public function removeFromList(string $listId, string $email): bool
    {
        $profileId = $this->getProfileIdByEmail($email);

        if (! $profileId) {
            return false;
        }

        $payload = [
            'data' => [
                [
                    'type' => 'profile',
                    'id' => $profileId,
                ],
            ],
        ];

        $response = $this->sendRequest(
            'DELETE',
            "/lists/{$listId}/relationships/profiles/",
            $payload
        );

        return $response->successful();
    }

    // ============================================
    // METRICS & ANALYTICS
    // ============================================

    public function getMetrics(): array
    {
        $response = $this->sendRequest('GET', '/metrics/');

        return $response->successful() ? $response->json() : [];
    }

    public function getMetric(string $metricId): ?array
    {
        $response = $this->sendRequest('GET', "/metrics/{$metricId}/");

        return $response->successful() ? $response->json() : null;
    }

    // ============================================
    // HTTP CLIENT
    // ============================================

    private function sendRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $queryParams = []
    ): Response {
        $http = Http::withHeaders([
            'Authorization' => 'Klaviyo-API-Key '.$this->apiKey,
            'Content-Type' => 'application/json',
            'revision' => $this->apiVersion,
        ])
            ->timeout(30);

        // Retry solo su errori 5xx, non 4xx
        $http->retry(2, 100, function ($exception) {
            return $exception->response && $exception->response->status() >= 500;
        });

        $url = $this->apiUrl.$endpoint;

        return match ($method) {
            'GET' => $http->get($url, $queryParams),
            'POST' => $http->post($url, $payload),
            'PATCH' => $http->patch($url, $payload),
            'DELETE' => $http->delete($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
        };
    }

    private function handleError(Response $response, string $method, string $context): void
    {
        $error = [
            'method' => $method,
            'context' => $context,
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        Log::error('Klaviyo API error', $error);

        throw new \Exception(
            "Klaviyo API {$method} failed with status {$response->status()}: {$response->body()}"
        );
    }
}
