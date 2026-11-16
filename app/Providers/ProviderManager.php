<?php
declare(strict_types=1);

namespace App\Providers;

use App\Models\ProviderModel;

final class ProviderManager
{
    public static function resolve(string $code): PaymentProviderInterface
    {
        return match ($code) {
            'famgateway' => new FamGatewayProvider(),
            default => throw new ProviderException('Unsupported payment provider: ' . $code, 500, 'unsupported_provider'),
        };
    }

    public static function resolveRow(array $provider): PaymentProviderInterface
    {
        return match ($provider['driver']) {
            'FamGatewayProvider' => new FamGatewayProvider((string) $provider['base_url']),
            default => throw new ProviderException('Provider driver is not implemented: ' . $provider['driver'], 500, 'unsupported_provider'),
        };
    }

    public static function credentialsFor(array $provider, int $merchantId): ?array
    {
        $credential = ProviderModel::credential((int) $provider['id'], $merchantId);
        return $credential['credentials'] ?? null;
    }

    public static function mergedCredentialsFor(array $provider, int $merchantId): ?array
    {
        $credential = ProviderModel::credential((int) $provider['id'], $merchantId);
        if (!$credential || empty($credential['credentials'])) {
            return null;
        }
        return [
            'api_key' => $credential['credentials']['api_key'],
            'credential_id' => $credential['id'],
            'scope' => $credential['merchant_id'] === null ? 'platform' : 'merchant',
        ];
    }
}
