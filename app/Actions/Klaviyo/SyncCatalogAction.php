<?php

namespace App\Actions\Klaviyo;

use App\DTO\Klaviyo\ProductDTO;
use App\Services\KlaviyoService;

class SyncCatalogAction
{
    public function __construct(
        private KlaviyoService $klaviyo
    ) {}

    public function syncSingle(ProductDTO $product): bool
    {
        return $this->klaviyo->upsertCatalogItem($product);
    }

    public function syncBulk(array $products): array
    {
        return $this->klaviyo->bulkUpsertCatalog($products);
    }

    public function syncFromSyliusArray(array $productData): bool
    {
        $product = ProductDTO::fromSylius($productData);

        return $this->syncSingle($product);
    }

    public function delete(string $productId): bool
    {
        return $this->klaviyo->deleteCatalogItem($productId);
    }
}
