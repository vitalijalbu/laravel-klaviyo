<?php

namespace App\Actions\Klaviyo;

use App\DTO\Klaviyo\CustomerDTO;
use App\Services\KlaviyoService;

class IdentifyCustomerAction
{
    public function __construct(
        private KlaviyoService $klaviyo
    ) {}

    public function execute(CustomerDTO $customer): bool
    {
        return $this->klaviyo->identify($customer);
    }

    public function executeFromArray(array $customerData): bool
    {
        $customer = CustomerDTO::fromArray($customerData);

        return $this->execute($customer);
    }
}
