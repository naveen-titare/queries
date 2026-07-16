<?php

namespace App\Services\Vouchers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Xoxoday Plum Pro "oauth/api" endpoint.
 * Every core API is a POST with { query, tag, variables:{ data:{...} } }.
 *
 * Each public method returns the inner `data` payload already unwrapped,
 * or throws RuntimeException with a readable message on failure.
 */
class XoxodayClient
{
    private string $url;
    private string $tag;
    private int $timeout;

    public function __construct()
    {
        $cfg = config('xoxoday');
        $this->url     = $cfg['base_url'] . $cfg['api_path'];
        $this->tag     = $cfg['tag'];
        $this->timeout = $cfg['timeout'];
    }

    /** Get filters (country, brand, category). */
    public function getFilters(string $filterGroupCode = 'Country', array $extra = []): array
    {
        return $this->call('plumProAPI.mutation.getFilters', array_merge([
            'filterGroupCode' => $filterGroupCode,
            'includeFilters'  => '',
            'excludeFilters'  => '',
        ], $extra), 'getFilters');
    }

    /** Get vouchers for a country / filters. */
    public function getVouchers(array $data = []): array
    {
        $payload = array_merge([
            'limit'           => 50,
            'page'            => 1,
            'includeProducts' => '',
            'excludeProducts' => '',
            'exchangeRate'    => 1,
            'sort'            => ['field' => 'name', 'order' => 'ASC'],
        ], $data);

        return $this->call('plumProAPI.mutation.getVouchers', $payload, 'getVouchers');
    }

    /** Wallet balance for the authenticated client. */
    public function getBalance(): array
    {
        return $this->call('plumProAPI.query.getBalance', new \stdClass(), 'getBalance');
    }

    /** Place an order for a specific product + denomination + quantity. */
    public function placeOrder(array $data): array
    {
        $cfg = config('xoxoday');
        $payload = array_merge([
            'email'               => $cfg['order_email'],
            'contact'             => $cfg['order_contact'],
            'tag'                 => 'AvirqoImport',
            'notifyReceiverEmail' => 0,
            'notifyAdminEmail'    => 0,
        ], $data);

        return $this->call('plumProAPI.mutation.placeOrder', $payload, 'placeOrder');
    }

    /** Order details by orderId or poNumber. */
    public function getOrderDetails(array $data): array
    {
        return $this->call('plumProAPI.mutation.getOrderDetails', $data, 'getOrderDetails');
    }

    /** Order history within a date range. */
    public function getOrderHistory(array $data): array
    {
        return $this->call('plumProAPI.mutation.getOrderHistory', $data, 'getOrderHistory');
    }

    /** Payment report for reconciliation. */
    public function getPaymentReport(array $data): array
    {
        return $this->call('plumProAPI.mutation.paymentHistory', $data, 'getPaymentReport');
    }

    /**
     * Core POST call. Unwraps the response and normalises errors.
     */
    private function call(string $query, array|object $data, string $responseKey): array
    {
        $token = config('xoxoday.access_token');
        if (empty($token)) {
            throw new RuntimeException('Xoxoday access token is not configured.');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($token)
                ->acceptJson()
                ->post($this->url, [
                    'query'     => $query,
                    'tag'       => $this->tag,
                    'variables' => ['data' => $data],
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Xoxoday connection failed: ' . $e->getMessage());
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Xoxoday HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $json = $response->json();

        if (isset($json['errors'])) {
            $msg = $json['errors'][0]['message'] ?? 'Unknown Xoxoday error';
            throw new RuntimeException('Xoxoday: ' . $msg);
        }

        $node = $json['data'][$responseKey] ?? null;
        if ($node === null) {
            throw new RuntimeException('Xoxoday: unexpected response shape for ' . $responseKey);
        }

        if (isset($node['status']) && (int) $node['status'] !== 1) {
            $msg = $node['message'] ?? ($node['msg'] ?? 'Request rejected by Xoxoday');
            throw new RuntimeException('Xoxoday: ' . $msg);
        }

        return $node['data'] ?? [];
    }
}
