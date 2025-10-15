<?php

namespace App\DTO\Klaviyo;

class ProductDTO
{
    public function __construct(
        public readonly string|int $id,
        public readonly string $title,
        public readonly float $price,
        public readonly string $currency,
        public readonly ?string $url = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $description = null,
        public readonly ?array $categories = [],
        public readonly ?string $sku = null,
        public readonly ?array $customAttributes = []
    ) {}

    public static function fromSylius(array $data): self
    {
        return new self(
            id: $data['product_id'],
            title: $data['product_name'],
            price: (float) $data['price'],
            currency: $data['currency'] ?? 'EUR',
            url: $data['product_url'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            description: $data['description'] ?? null,
            categories: $data['categories'] ?? [],
            sku: $data['sku'] ?? null,
            customAttributes: $data['custom_attributes'] ?? []
        );
    }

    public function toEventProperties(): array
    {
        return array_filter([
            'product_id' => $this->id,
            'product_name' => $this->title,
            'price' => $this->price,
            'currency' => $this->currency,
            'product_url' => $this->url,
            'image_url' => $this->imageUrl,
            'categories' => $this->categories,
            'sku' => $this->sku,
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function toCatalogItem(): array
    {
        return [
            'type' => 'catalog-item',
            'id' => "\$custom:::\$default:::{$this->id}",
            'attributes' => array_filter([
                'external_id' => (string) $this->id,
                'title' => $this->title,
                'price' => $this->price,
                'url' => $this->url,
                'image_full_url' => $this->imageUrl,
                'description' => $this->description,
                'published' => true,
                'custom_metadata' => array_merge([
                    'currency' => $this->currency,
                    'categories' => $this->categories,
                    'sku' => $this->sku,
                ], $this->customAttributes),
            ], fn ($value) => $value !== null),
        ];
    }
}
