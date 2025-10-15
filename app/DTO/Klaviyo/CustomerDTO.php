<?php

namespace App\DTO\Klaviyo;

class CustomerDTO
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?string $title = null,
        public readonly ?string $organization = null,
        public readonly ?array $properties = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            title: $data['title'] ?? null,
            organization: $data['organization'] ?? null,
            properties: $data['properties'] ?? []
        );
    }

    public function toKlaviyoFormat(): array
    {
        $data = array_filter([
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone_number' => $this->phoneNumber,
            'title' => $this->title,
            'organization' => $this->organization,
        ], fn ($value) => $value !== null);

        return array_merge($data, $this->properties);
    }
}
