<?php
declare(strict_types=1);

namespace App\Providers;

interface PaymentProviderInterface
{
    /**
     * Create a hosted payment order.
     * @param array{api_key:string} $credentials
     * @return array{provider_order_id:string,amount:float,payable_amount:float,qr_url:?string,checkout_url:?string,upi_intent:?string,upi_id:?string,expires_at:string,raw:array}
     */
    public function createPayment(array $credentials, array $payload): array;

    /**
     * Authoritative server-to-server status check.
     * @return array{status:string,provider_order_id:string,amount:?float,payable_amount:?float,utr:?string,transaction_id:?string,sender_name:?string,paid_at:?string,raw:array}
     */
    public function getPaymentStatus(array $credentials, string $providerOrderId): array;

    /**
     * Verify the provider webhook signature before trusting any field.
     * @return array{event:string,order_id:string,utr:?string,amount:?string,status:string,raw:array}
     */
    public function processWebhook(string $rawBody, ?string $signature, array $credentials): array;

    /**
     * Test credentials against an authenticated read-only endpoint.
     * @return array{connected:bool,message:string,latency_ms:int}
     */
    public function testConnection(array $credentials): array;

    public function supportsRefunds(): bool;

    /**
     * @return array{status:string,message:string}
     */
    public function getRefundStatus(array $credentials, string $providerOrderId): array;
}
