<?php

namespace App\Actions\Klaviyo;

use App\Services\KlaviyoService;

class DeleteProfileAction
{
    public function __construct(
        private KlaviyoService $klaviyo
    ) {}

    public function execute(string $email): bool
    {
        return $this->klaviyo->deleteProfile($email);
    }
}
