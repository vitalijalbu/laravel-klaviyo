<?php

namespace App\DTO\Klaviyo;

use Carbon\Carbon;

class EventDTO
{
    public function __construct(
        public readonly string $event,
        public readonly array $properties,
        public readonly ?CustomerDTO $customer = null,
        public readonly ?Carbon $time = null,
        public readonly ?string $uniqueId = null
    ) {}

    public static function create(
        string $event,
        array $properties,
        ?array $customerData = null
    ): self {
        return new self(
            event: $event,
            properties: $properties,
            customer: $customerData ? CustomerDTO::fromArray($customerData) : null,
            time: now()
        );
    }

    public function toKlaviyoPayload(): array
    {
        $payload = [
            'data' => [
                'type' => 'event',
                'attributes' => [
                    'metric' => ['name' => $this->event],
                    'properties' => $this->properties,
                    'time' => ($this->time ?? now())->toIso8601String(),
                ],
            ],
        ];

        if ($this->customer) {
            $payload['data']['attributes']['profile'] = $this->customer->toKlaviyoFormat();
        }

        if ($this->uniqueId) {
            $payload['data']['attributes']['unique_id'] = $this->uniqueId;
        }

        return $payload;
    }
}
