<?php
declare(strict_types=1);

namespace App\Providers;

final class IntegrationNotConfiguredException extends ProviderException
{
    public function __construct(string $message = 'Payment integration is not configured for this merchant.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 409, 'integration_not_configured', $previous);
    }
}
