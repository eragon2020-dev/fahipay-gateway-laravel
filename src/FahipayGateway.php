<?php

namespace Fahipay\Gateway;

use Fahipay\Gateway\Contracts\GatewayInterface;
use Fahipay\Gateway\Data\PaymentData;
use Fahipay\Gateway\Data\TransactionData;
use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Events\PaymentCancelledEvent;
use Fahipay\Gateway\Events\PaymentCompletedEvent;
use Fahipay\Gateway\Events\PaymentFailedEvent;
use Fahipay\Gateway\Events\PaymentInitiatedEvent;
use Fahipay\Gateway\Events\PaymentPendingEvent;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Support\SignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class FahipayGateway
{
    protected ?string $shopId = null;
    protected ?string $secretKey = null;
    protected string $baseUrl = 'https://fahipay.mv/api/merchants';
    protected string $webUrl = 'https://fahipay.mv';
    protected string $testBaseUrl = 'https://test.fahipay.mv/api/merchants';
    protected bool $testMode = false;
    protected ?string $returnUrl = null;
    protected ?string $cancelUrl = null;
    protected ?string $errorUrl = null;

    protected ?string $lastTransactionId = null;
    protected ?array $lastResponse = null;

    protected array $config = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $this->config = config('fahipay', []);
        
        $this->shopId = $this->config['shop_id'] ?? null;
        $this->secretKey = $this->config['secret_key'] ?? null;
        $this->testMode = $this->config['test_mode'] ?? false;
        $this->returnUrl = $this->config['return_url'] ?? null;
        $this->cancelUrl = $this->config['cancel_url'] ?? null;
        $this->errorUrl = $this->config['error_url'] ?? null;
        
        $this->baseUrl = $this->testMode 
            ? ($this->config['test_base_url'] ?? $this->testBaseUrl)
            : ($this->config['base_url'] ?? $this->baseUrl);
    }

    public function setShopId(string $shopId): self
    {
        $this->shopId = $shopId;
        return $this;
    }

    /**
     * @deprecated Use setShopId() instead
     */
    public function setMerchantId(string $merchantId): self
    {
        $this->shopId = $merchantId;
        return $this;
    }

    /**
     * @deprecated Use getShopId() instead
     */
    public function getMerchantId(): ?string
    {
        return $this->getShopId();
    }

    public function getShopId(): ?string
    {
        return $this->shopId;
    }

    public function setSecretKey(string $secretKey): self
    {
        $this->secretKey = $secretKey;
        return $this;
    }

    public function setTestMode(bool $testMode = true): self
    {
        $this->testMode = $testMode;
        $this->baseUrl = $testMode ? $this->testBaseUrl : 'https://fahipay.mv/api/merchants';
        return $this;
    }

    public function setReturnUrl(string $returnUrl): self
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }

    public function setCancelUrl(string $cancelUrl): self
    {
        $this->cancelUrl = $cancelUrl;
        return $this;
    }

    public function setErrorUrl(string $errorUrl): self
    {
        $this->errorUrl = $errorUrl;
        return $this;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getWebUrl(): string
    {
        return $this->webUrl;
    }

    /**
     * Create a new payment transaction
     * 
     * Uses the API endpoint: POST https://fahipay.mv/api/merchants/createTxn/
     */
    public function createPayment(string $transactionId, float $amount, ?string $description = null, ?array $metadata = []): PaymentData
    {
        if (empty($this->shopId) || empty($this->secretKey)) {
            throw new FahipayException('Shop ID and Secret Key are required');
        }

        // Amount must be in cents (last 2 digits = decimal places)
        // Example: 19.00 = 1900, 19.50 = 1950
        $amountInCents = (int) round($amount * 100);
        
        $signature = $this->generateSignature($transactionId, $amountInCents);

        $data = [
            'ShopID' => $this->shopId,
            'ShoppingCartID' => $transactionId,
            'TotalAmount' => $amountInCents,
            'Signature' => $signature,
            'ReturnURL' => $this->returnUrl,
            'ReturnErrorURL' => $this->errorUrl,
            'CancelURL' => $this->cancelUrl,
        ];

        if ($description) {
            $data['ShoppingCartDesc'] = $description;
        }

        $response = $this->makeRequest('/createTxn/', $data);

        $this->lastTransactionId = $transactionId;
        $this->lastResponse = $response;

        Event::dispatch(new PaymentInitiatedEvent(
            $transactionId,
            $amount,
            $response
        ));

        if ($response['type'] ?? '' === 'success') {
            Cache::put("fahipay_payment_{$transactionId}", [
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], now()->addHours(24));
        }

        return new PaymentData(
            transactionId: $transactionId,
            amount: $amount,
            status: PaymentStatus::PENDING,
            paymentUrl: $response['link'] ?? null,
            rawResponse: $response
        );
    }

    /**
     * Create payment with array config
     */
    public function create(array $config): PaymentData
    {
        return $this->createPayment(
            $config['transaction_id'],
            $config['amount'],
            $config['description'] ?? null,
            $config['metadata'] ?? []
        );
    }

    /**
     * Get payment URL for redirect
     * FahiPay requires a POST to /payment/ which returns a redirect to the payment page
     */
    public function getPaymentUrl(string $transactionId, float $amount): string
    {
        $amountInCents = (int) round($amount * 100);
        $signature = $this->generateSignature($transactionId, $amountInCents);

        $webUrl = $this->testMode 
            ? 'https://test.fahipay.mv/payment/'
            : 'https://fahipay.mv/payment/';

        $params = [
            'ShopID' => $this->shopId,
            'ShoppingCartID' => $transactionId,
            'TotalAmount' => $amountInCents,
            'Signature' => $signature,
            'ReturnURL' => $this->returnUrl,
            'ReturnErrorURL' => $this->errorUrl,
            'CancelURL' => $this->cancelUrl,
        ];

        $cookiePath = storage_path('fahipay/cookies_' . uniqid() . '.txt');
        if (!is_dir(dirname($cookiePath))) {
            mkdir(dirname($cookiePath), 0755, true);
        }

        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $webUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiePath);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiePath);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        
        $redirectUrl = null;
        if ($info['http_code'] == 302 || $info['http_code'] == 301) {
            preg_match('/Location: (.*)/i', $response, $matches);
            $redirectUrl = isset($matches[1]) ? trim($matches[1]) : null;
        }
        
        curl_close($ch);
        
        // Clean up temporary cookie file
        @unlink($cookiePath);
        
        if ($redirectUrl && $this->isValidRedirectUrl($redirectUrl)) {
            return $redirectUrl;
        }
        
        return $webUrl . '?' . http_build_query($params);
    }

    /**
     * Validate redirect URL to prevent SSRF attacks
     */
    protected function isValidRedirectUrl(string $url): bool
    {
        $allowedHosts = [
            'fahipay.mv',
            'test.fahipay.mv',
            'www.fahipay.mv',
            'pay.fahipay.mv',
        ];

        $parsed = parse_url($url);
        
        if (empty($parsed['host'])) {
            return false;
        }

        return in_array($parsed['host'], $allowedHosts, true);
    }

    /**
     * Query transaction status
     * Uses the API: GET https://fahipay.mv/api/merchants/getTxn/?mref=<ShoppingCartID>
     */
    public function getTransaction(string $transactionId): ?TransactionData
    {
        $timestamp = time();
        $signature = $this->generateQuerySignature($transactionId, $timestamp);

        $response = $this->makeRequest('/getTxn/?mref=' . urlencode($transactionId), [
            'timestamp' => $timestamp,
        ]);

        if (($response['type'] ?? '') === 'success' && isset($response['data'])) {
            return new TransactionData(
                transactionId: $response['data']['mref'] ?? $transactionId,
                amount: (float) ($response['data']['amount'] ?? 0),
                status: $this->mapStatus($response['data']['status'] ?? 'unknown'),
                method: $response['data']['method'] ?? null,
                approvalCode: $response['data']['ApprovalCode'] ?? null,
                time: isset($response['data']['time']) ? \Carbon\Carbon::parse($response['data']['time']) : null,
                rawResponse: $response
            );
        }

        return null;
    }

    /**
     * Query payment status by Approval Code
     */
    public function getPayment(string $transactionId): ?TransactionData
    {
        return $this->getTransaction($transactionId);
    }

    /**
     * Verify callback signature
     * Uses HMAC-SHA256 for secure signature verification
     */
    public function verifySignature(string $success, string $transactionId, ?string $approvalCode, string $signature): bool
    {
        // Success: "1" for success, "0" for failure
        $successValue = ($success === 'true' || $success === '1') ? '1' : '0';
        
        $signatureData = $this->shopId . $this->secretKey . $transactionId . $this->secretKey . $successValue . $this->secretKey . ($approvalCode ?? '') . $this->secretKey;
        $expectedSignature = base64_encode(hash_hmac('sha256', $signatureData, $this->secretKey, true));
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate callback from FahiPay
     * Includes replay attack protection by checking timestamp
     */
    public function validateCallback(Request $request): bool
    {
        $success = $request->get('Success', 'false');
        $transactionId = $request->get('ShoppingCartID', '');
        $approvalCode = $request->get('ApprovalCode');
        $signature = $request->get('Signature', '');
        
        // Replay attack protection: check if timestamp exists and is not expired
        $timestamp = $request->get('Timestamp');
        if ($timestamp !== null) {
            if ($this->isSignatureExpired((int) $timestamp)) {
                Log::warning('FahiPay: Callback timestamp expired (replay attack prevention)', [
                    'transaction_id' => $transactionId,
                    'timestamp' => $timestamp,
                ]);
                return false;
            }
        }

        return $this->verifySignature($success, $transactionId, $approvalCode, $signature);
    }

    /**
     * Handle callback from FahiPay
     */
    public function handleCallback(Request $request): TransactionData
    {
        $success = $request->get('Success', 'false');
        $transactionId = $request->get('ShoppingCartID', '');
        $approvalCode = $request->get('ApprovalCode');
        $signature = $request->get('Signature', '');

        if (!$this->verifySignature($success, $transactionId, $approvalCode, $signature)) {
            Log::error('FahiPay: Invalid signature', [
                'transaction_id' => $transactionId,
                'signature_received' => $signature,
            ]);
            
            throw new FahipayException('Invalid signature');
        }

        $status = $success === 'true' ? PaymentStatus::COMPLETED : PaymentStatus::FAILED;

        // Retrieve payment amount from cache for accurate transaction data
        $paymentData = Cache::get("fahipay_payment_{$transactionId}");
        $amount = 0;
        if ($paymentData && isset($paymentData['amount'])) {
            $amount = $paymentData['amount'];
        } elseif ($request->has('TotalAmount')) {
            // Fallback: try to get from callback request (in cents)
            $amount = (int) $request->get('TotalAmount', 0) / 100;
        }

        Event::dispatch(match ($status) {
            PaymentStatus::COMPLETED => new PaymentCompletedEvent($transactionId, $approvalCode),
            PaymentStatus::FAILED => new PaymentFailedEvent($transactionId),
            default => new PaymentPendingEvent($transactionId),
        });

        Cache::forget("fahipay_payment_{$transactionId}");

        return new TransactionData(
            transactionId: $transactionId,
            amount: $amount,
            status: $status,
            approvalCode: $approvalCode,
            rawResponse: $request->all()
        );
    }

    /**
     * Process webhook
     */
    public function processWebhook(Request $request): TransactionData
    {
        return $this->handleCallback($request);
    }

    /**
     * Generate payment link (for direct redirect)
     * Note: This method places sensitive data (ShopID, Signature) in query parameters.
     * This is required by the FahiPay API specification. Consider using POST-based
     * payment flows for better security if the API supports it.
     */
    public function getLink(string $transactionId, float $amount): string
    {
        // Amount in cents
        $amountInCents = (int) round($amount * 100);
        $signature = $this->generateSignature($transactionId, $amountInCents);

        $params = http_build_query([
            'ShopID' => $this->shopId,
            'ShoppingCartID' => $transactionId,
            'TotalAmount' => $amountInCents,
            'Signature' => $signature,
            'ReturnURL' => $this->returnUrl,
            'ReturnErrorURL' => $this->errorUrl,
            'CancelURL' => $this->cancelUrl,
        ]);

        return "{$this->webUrl}/pay?{$params}";
    }

    /**
     * Generate signature for payment creation
     * Uses HMAC-SHA256 for secure signature generation
     */
    public function generateSignature(string $transactionId, int $amountInCents, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $signatureData = $this->shopId . $this->secretKey . $transactionId . $this->secretKey . $amountInCents . $this->secretKey . $timestamp . $this->secretKey;
        return base64_encode(hash_hmac('sha256', $signatureData, $this->secretKey, true));
    }

    /**
     * Generate signature for query requests
     * Includes timestamp to prevent signature replay attacks
     */
    protected function generateQuerySignature(string $transactionId, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $signatureData = $this->shopId . $this->secretKey . $transactionId . $this->secretKey . $timestamp . $this->secretKey;
        return base64_encode(hash_hmac('sha256', $signatureData, $this->secretKey, true));
    }

    /**
     * Verify that a signature has not expired (replay attack protection)
     */
    public function isSignatureExpired(int $timestamp, int $validitySeconds = 300): bool
    {
        return (time() - $timestamp) > $validitySeconds;
    }

    /**
     * Extract timestamp from signature data
     * This is a helper for backward compatibility with the API
     */
    public function extractTimestampFromSignature(string $signature): ?int
    {
        return null;
    }

    /**
     * Make API request
     */
    protected function makeRequest(string $endpoint, array $data): array
    {
        try {
            $response = Http::timeout($this->config['timeout'] ?? 30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('FahiPay API Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'type' => 'error',
                'message' => $response->json('message') ?? $response->json('msg') ?? 'Request failed',
                'code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('FahiPay Request Exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'type' => 'error',
                'message' => $e->getMessage(),
                'code' => 0,
            ];
        }
    }

    /**
     * Map status from API to enum
     */
    protected function mapStatus(string $status): PaymentStatus
    {
        return match (strtolower($status)) {
            'completed', 'success' => PaymentStatus::COMPLETED,
            'pending' => PaymentStatus::PENDING,
            'failed', 'error' => PaymentStatus::FAILED,
            'cancelled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::UNKNOWN,
        };
    }

    /**
     * Get last transaction ID
     */
    public function getLastTransactionId(): ?string
    {
        return $this->lastTransactionId;
    }

    /**
     * Get last response
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }

    /**
     * Check if payment is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->shopId) && !empty($this->secretKey);
    }

    /**
     * Get config
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Setup routes for the package
     */
    public function routes(?string $prefix = 'fahipay'): void
    {
        require __DIR__ . '/../routes/web.php';
    }
}