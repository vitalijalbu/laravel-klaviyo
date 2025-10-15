<?php

namespace App\DTO\Klaviyo;

class OrderDTO
{
    public function __construct(
        public readonly string|int $orderId,
        public readonly string $orderNumber,
        public readonly float $total,
        public readonly string $currency,
        public readonly array $items,
        public readonly ?float $subtotal = null,
        public readonly ?float $tax = null,
        public readonly ?float $shipping = null,
        public readonly ?float $discount = null,
        public readonly ?array $billingAddress = null,
        public readonly ?array $shippingAddress = null,
        public readonly ?string $status = null
    ) {}

    public static function fromSylius(array $data): self
    {
        return new self(
            orderId: $data['order_id'],
            orderNumber: $data['order_number'],
            total: (float) $data['total'],
            currency: $data['currency'] ?? 'EUR',
            items: $data['items'] ?? [],
            subtotal: isset($data['subtotal']) ? (float) $data['subtotal'] : null,
            tax: isset($data['tax']) ? (float) $data['tax'] : null,
            shipping: isset($data['shipping']) ? (float) $data['shipping'] : null,
            discount: isset($data['discount']) ? (float) $data['discount'] : null,
            billingAddress: $data['billing_address'] ?? null,
            shippingAddress: $data['shipping_address'] ?? null,
            status: $data['status'] ?? 'completed'
        );
    }

    public function toEventProperties(): array
    {
        return array_filter([
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'value' => $this->total,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'shipping' => $this->shipping,
            'discount' => $this->discount,
            'items' => $this->items,
            'item_count' => count($this->items),
            'billing_address' => $this->billingAddress,
            'shipping_address' => $this->shippingAddress,
            'status' => $this->status,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
