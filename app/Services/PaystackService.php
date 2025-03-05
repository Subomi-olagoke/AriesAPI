<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class PaystackService
{
    protected $secretKey;
    protected $baseUrl = 'https://api.paystack.co';
    protected $publicKey;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
    }

    /**
     * Initialize a transaction for one-time payment
     *
     * @param string $email Customer email
     * @param float $amount Amount in naira
     * @param string $callbackUrl URL to redirect after payment
     * @param array $metadata Additional data to store with transaction
     * @return array
     */
    public function initializeTransaction($email, $amount, $callbackUrl, $metadata = [])
    {
        return $this->makeRequest('POST', '/transaction/initialize', [
            'email' => $email,
            'amount' => $amount * 100, // Convert to kobo
            'callback_url' => $callbackUrl,
            'metadata' => $metadata
        ]);
    }

    /**
     * Initialize a subscription
     *
     * @param string $email Customer email
     * @param string $planCode Paystack plan code
     * @param string $callbackUrl URL to redirect after payment
     * @param array $metadata Additional data to store with subscription
     * @return array
     */
    public function initializeSubscription($email, $planCode, $callbackUrl, $metadata = [])
    {
        return $this->makeRequest('POST', '/transaction/initialize', [
            'email' => $email,
            'plan' => $planCode,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata
        ]);
    }

    /**
     * Create a subscription plan
     *
     * @param string $name Plan name
     * @param float $amount Amount in naira
     * @param string $interval Plan interval (daily, weekly, monthly, annually)
     * @param string $description Plan description
     * @return array
     */
    public function createPlan($name, $amount, $interval, $description = '')
    {
        return $this->makeRequest('POST', '/plan', [
            'name' => $name,
            'amount' => $amount * 100, // Convert to kobo
            'interval' => $interval,
            'description' => $description
        ]);
    }

    /**
     * List plans
     *
     * @param int $perPage Number of records per page
     * @param int $page Page number
     * @return array
     */
    public function listPlans($perPage = 50, $page = 1)
    {
        return $this->makeRequest('GET', "/plan?perPage={$perPage}&page={$page}");
    }

    /**
     * Verify a transaction
     *
     * @param string $reference Transaction reference
     * @return array
     */
    public function verifyTransaction($reference)
    {
        return $this->makeRequest('GET', "/transaction/verify/{$reference}");
    }

    /**
     * Get transaction details
     *
     * @param int $id Transaction ID
     * @return array
     */
    public function fetchTransaction($id)
    {
        return $this->makeRequest('GET', "/transaction/{$id}");
    }

    /**
     * List transactions
     *
     * @param int $perPage Number of records per page
     * @param int $page Page number
     * @return array
     */
    public function listTransactions($perPage = 50, $page = 1)
    {
        return $this->makeRequest('GET', "/transaction?perPage={$perPage}&page={$page}");
    }

    /**
     * Get subscription details
     *
     * @param string $idOrCode Subscription ID or code
     * @return array
     */
    public function fetchSubscription($idOrCode)
    {
        return $this->makeRequest('GET', "/subscription/{$idOrCode}");
    }

    /**
     * Enable a subscription
     *
     * @param string $code Subscription code
     * @param string $token Email token
     * @return array
     */
    public function enableSubscription($code, $token)
    {
        return $this->makeRequest('POST', '/subscription/enable', [
            'code' => $code,
            'token' => $token
        ]);
    }

    /**
     * Disable a subscription
     *
     * @param string $code Subscription code
     * @param string $token Email token
     * @return array
     */
    public function disableSubscription($code, $token)
    {
        return $this->makeRequest('POST', '/subscription/disable', [
            'code' => $code,
            'token' => $token
        ]);
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Request body
     * @param string $signature Signature from header
     * @return bool
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        return hash_equals(
            hash_hmac('sha512', $payload, $this->secretKey),
            $signature
        );
    }

    /**
     * Make HTTP request to Paystack API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array
     */
    protected function makeRequest($method, $endpoint, $data = [])
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->$method($this->baseUrl . $endpoint, $method === 'GET' ? [] : $data);

            if (!$response->successful()) {
                Log::error('Paystack API error: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Payment service error: ' . $response->json('message', 'Unknown error'),
                    'status_code' => $response->status()
                ];
            }

            return [
                'success' => true,
                'data' => $response->json()
            ];
        } catch (RequestException $e) {
            Log::error('Paystack request error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment service connection error',
                'exception' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Paystack unexpected error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred',
                'exception' => $e->getMessage()
            ];
        }
    }
}