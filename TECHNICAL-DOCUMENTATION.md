# FahiPay Gateway Laravel Package - Technical Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Core Components](#core-components)
4. [Workflow](#workflow)
5. [API Reference](#api-reference)
6. [Security](#security)

---

## 1. Overview

The **fahipaydev/gateway-laravel** package provides a seamless integration with FahiPay payment gateway for Laravel applications. It handles payment initiation, callback processing, and transaction verification.

**Package**: https://github.com/eragon2020-dev/fahipay-gateway-laravel

---

## 2. Architecture

### Directory Structure

```
src/
├── FahipayGateway.php          # Main gateway class
├── FahipayGatewayServiceProvider.php  # Laravel service provider
├── Facades/
│   └── FahipayGateway.php      # Static facade for easy access
├── Contracts/
│   ├── GatewayInterface.php    # Payment gateway contract
│   └── PaymentHandlerInterface.php
├── Data/
│   ├── PaymentData.php        # Payment response DTO
│   └── TransactionData.php    # Transaction response DTO
├── Enums/
│   └── PaymentStatus.php       # Payment status enum
├── Events/
│   ├── PaymentInitiatedEvent.php
│   ├── PaymentCompletedEvent.php
│   ├── PaymentFailedEvent.php
│   ├── PaymentPendingEvent.php
│   └── PaymentCancelledEvent.php
├── Exceptions/
│   └── FahipayException.php    # Custom exception
├── Http/
│   ├── Controllers/
│   │   ├── WebhookController.php
│   │   └── Api/
│   │       ├── PaymentController.php
│   │       └── WebhookController.php
│   ├── Middleware/
│   │   └── VerifyWebhookSignature.php
│   ├── Requests/
│   │   ├── CreatePaymentRequest.php
│   │   └── HandleCallbackRequest.php
│   └── Resources/
│       ├── PaymentResource.php
│       └── TransactionResource.php
├── Models/
│   └── FahipayPayment.php     # Eloquent model for payments
├── Actions/
│   ├── CreatePaymentAction.php
│   ├── VerifyPaymentAction.php
│   └── ProcessCallbackAction.php
├── Jobs/
│   ├── ExpirePendingPaymentJob.php
│   ├── RetryFailedPaymentJob.php
│   └── ProcessPaymentJob.php
├── Console/
│   ├── InstallCommand.php
│   ├── CreatePaymentCommand.php
│   └── CheckPaymentCommand.php
├── Mail/
│   ├── PaymentConfirmationMail.php
│   └── PaymentReceivedMail.php
├── Notifications/
│   ├── PaymentReceivedNotification.php
│   └── PaymentFailedNotification.php
├── Observers/
│   └── FahipayPaymentObserver.php
└── Support/
    └── helpers.php
```

---

## 3. Core Components

### 3.1 FahipayGateway.php (Main Class)

The core class that handles all FahiPay API interactions.

#### Properties

```php
protected ?string $shopId = null;        // Merchant Shop ID from FahiPay
protected ?string $secretKey = null;     // Merchant secret key
protected string $baseUrl = 'https://fahipay.mv/api/merchants';  // API URL
protected string $testBaseUrl = 'https://test.fahipay.mv/api/merchants'; // Test URL
protected bool $testMode = false;        // Test mode flag
protected ?string $returnUrl = null;     // Success callback URL
protected ?string $cancelUrl = null;     // Cancel callback URL
protected ?string $errorUrl = null;      // Error callback URL
```

#### Constructor & Configuration

```php
public function __construct()
{
    $this->loadConfig();
}

protected function loadConfig(): void
{
    // Loads configuration from config/fahipay.php and .env
    // Priority: config > env variables
}
```

The `loadConfig()` method reads configuration from:
1. `config/fahipay.php` 
2. Environment variables (`FAHIPAY_SHOP_ID`, `FAHIPAY_SECRET_KEY`, etc.)

#### Configuration Methods

| Method | Description |
|--------|-------------|
| `setShopId(string)` | Set merchant Shop ID |
| `setSecretKey(string)` | Set merchant secret key |
| `setTestMode(bool)` | Enable/disable test mode |
| `setReturnUrl(string)` | Set success callback URL |
| `setCancelUrl(string)` | Set cancel callback URL |
| `setErrorUrl(string)` | Set error callback URL |

---

### 3.2 createPayment() - Payment Initiation

```php
public function createPayment(
    string $transactionId,  // Unique order ID from merchant
    float $amount,          // Amount in MVR (not cents)
    ?string $description = null,
    ?array $metadata = []
): PaymentData
```

**How it works:**

1. **Validation**: Checks if Shop ID and Secret Key are set
2. **Amount Conversion**: Converts MVR to cents (multiply by 100)
   - Example: 19.50 MVR → 1950 cents
3. **Signature Generation**: Creates HMAC-SHA256+Base64 signature
   - Format: `Base64(HMAC-SHA256(ShopID + SecretKey + ShoppingCartID + SecretKey + Amount + SecretKey + Timestamp + SecretKey))`
4. **API Request**: Sends POST to `https://fahipay.mv/api/merchants/createTxn/`
5. **Event Dispatch**: Fires `PaymentInitiatedEvent`
6. **Cache Storage**: Stores payment data in cache for 24 hours
7. **Response**: Returns `PaymentData` object

---

### 3.3 getPaymentUrl() - Payment Page URL

```php
public function getPaymentUrl(string $transactionId, float $amount): string
```

**Critical Fix**: This method was fixed to properly handle FahiPay's redirect flow.

**How it works:**

1. **POST to Payment Endpoint**: Sends POST to `https://fahipay.mv/payment/` with all parameters
2. **Cookie Handling**: Uses cookies to maintain session
3. **Redirect Detection**: Checks for 301/302 redirect response
4. **Extract Redirect URL**: Parses `Location` header to get payment page URL
   - Format: `https://fahipay.mv/pay/L20260414014446XDU3F`
5. **Fallback**: If no redirect, returns direct URL (works when opened in browser)

**Why POST is required:**
- FahiPay's payment page requires a POST request
- Direct GET access returns: "You cannot access this page directly"
- The POST triggers a redirect to the actual payment page

---

### 3.4 getTransaction() - Query Payment Status

```php
public function getTransaction(string $transactionId): ?TransactionData
```

**API Endpoint**: `GET https://fahipay.mv/api/merchants/getTxn/?mref=<ShoppingCartID>`

**Response Mapping:**
- `mref` → transactionId
- `amount` → amount (in MVR)
- `status` → PaymentStatus enum (paid, pending, failed)
- `method` → payment method (gateway, etc.)
- `ApprovalCode` → approval code
- `time` → transaction timestamp
- `extras` → additional data (name, mobile)

---

### 3.5 verifySignature() - Signature Verification

```php
public function verifySignature(
    string $success,        // "1" for success, "0" for failure
    string $transactionId,  // ShoppingCartID
    ?string $approvalCode,  // FahiPay transaction ID
    string $signature       // Received signature
): bool
```

**Signature Format** (updated to use HMAC-SHA256):
```
Base64(HMAC-SHA256(ShopID + SecretKey + ShoppingCartID + SecretKey + Success + SecretKey + ApprovalCode + SecretKey))
```

**Security**: Uses `hash_equals()` for timing-safe comparison to prevent timing attacks.

---

### 3.6 validateCallback() - Validate Payment Callback

```php
public function validateCallback(Request $request): bool
```

Extracts callback parameters and verifies signature:
- `Success` - Payment result ("true" or "false")
- `ShoppingCartID` - Merchant transaction ID
- `ApprovalCode` - FahiPay transaction ID
- `Signature` - Response signature

---

### 3.7 handleCallback() - Process Payment Callback

```php
public function handleCallback(Request $request): TransactionData
```

**Process Flow:**
1. Verify signature (throws exception if invalid)
2. Determine status (COMPLETED or FAILED)
3. Dispatch appropriate event
4. Clear cached payment data
5. Return TransactionData object

---

## 4. Workflow

### 4.1 Payment Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         MERCHANT APPLICATION                             │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  1. User clicks "Pay"                                                  │
│  2. Controller calls FahipayGateway::getPaymentUrl($orderId, $amount) │
│  3. Gateway generates signature & POST to fahipay.mv/payment/         │
│  4. FahiPay redirects to payment page                                   │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      FAHIPAY PAYMENT PAGE                               │
│  https://fahipay.mv/pay/L20260414014446XDU3F                           │
│                                                                          │
│  User enters:                                                           │
│  - FahiPay username/phone                                               │
│  - Password/PIN                                                         │
│  - Confirms payment                                                    │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  5. Payment Success                                                    │
│  6. FahiPay redirects to ReturnURL with callback params:               │
│     ?Success=true                                                       │
│     &ShoppingCartID=ORDER123                                           │
│     &ApprovalCode=FP20260414014446KTG                                  │
│     &TotalAmount=1950                                                  │
│     &Signature=...                                                     │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  7. Merchant callback handler receives callback                        │
│  8. Calls $gateway->validateCallback($request)                         │
│  9. Verifies signature                                                  │
│ 10. Updates order status                                               │
│ 11. Dispatches PaymentCompletedEvent                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Step-by-Step Implementation

#### Step 1: Install Package

```bash
composer require fahipaydev/gateway-laravel
```

#### Step 2: Configure .env

```env
FAHIPAY_SHOP_ID=your_shop_id
FAHIPAY_SECRET_KEY=your_secret_key
FAHIPAY_TEST_MODE=false
FAHIPAY_RETURN_URL=https://yourapp.com/payment/callback
FAHIPAY_CANCEL_URL=https://yourapp.com/payment/cancelled
FAHIPAY_ERROR_URL=https://yourapp.com/payment/failed
```

#### Step 3: Publish Config

```bash
php artisan vendor:publish --provider="Fahipay\Gateway\FahipayGatewayServiceProvider"
```

#### Step 4: Use in Controller

```php
<?php

namespace App\Http\Controllers;

use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function processPayment(Request $request)
    {
        $orderId = 'ORDER-' . time();
        $amount = 100.00; // 100 MVR
        
        // Get payment URL (the key fix!)
        $paymentUrl = FahipayGateway::getPaymentUrl($orderId, $amount);
        
        // Redirect user to FahiPay
        return redirect($paymentUrl);
    }
    
    public function callback(Request $request)
    {
        // Validate callback
        if (!FahipayGateway::validateCallback($request)) {
            return response('Invalid signature', 400);
        }
        
        $success = $request->get('Success');
        $orderId = $request->get('ShoppingCartID');
        $approvalCode = $request->get('ApprovalCode');
        
        if ($success === 'true') {
            // Update order to "paid" status
            Order::where('order_number', $orderId)->update([
                'status' => 'paid',
                'approval_code' => $approvalCode,
            ]);
        }
        
        return response('OK');
    }
}
```

---

## 5. API Reference

### Facade Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `createPayment($id, $amount, $desc)` | Create payment transaction | `PaymentData` |
| `getPaymentUrl($id, $amount)` | Get redirect URL | `string` |
| `getTransaction($id)` | Query transaction status | `TransactionData?` |
| `validateCallback($request)` | Validate callback signature | `bool` |
| `handleCallback($request)` | Process callback | `TransactionData` |
| `verifySignature(...)` | Verify signature | `bool` |

### Data Transfer Objects

#### PaymentData
```php
[
    'transactionId' => string,
    'amount' => float,
    'status' => PaymentStatus,
    'paymentUrl' => string|null,
    'rawResponse' => array
]
```

#### TransactionData
```php
[
    'transactionId' => string,
    'amount' => float,
    'status' => PaymentStatus,
    'method' => string|null,
    'approvalCode' => string|null,
    'time' => Carbon|null,
    'rawResponse' => array
]
```

### PaymentStatus Enum

| Status | Description |
|--------|-------------|
| `PENDING` | Payment initiated, awaiting completion |
| `COMPLETED` | Payment successful |
| `FAILED` | Payment failed |
| `CANCELLED` | Payment cancelled by user |

---

## 6. Security

### 6.1 Signature Generation

The package uses HMAC-SHA256 + Base64 encoding for signatures (upgraded from SHA1 for security):

```php
// Request signature (with timestamp for replay protection)
$sigData = $shopId . $secretKey . $transactionId . $secretKey . $amount . $secretKey . $timestamp . $secretKey;
$signature = base64_encode(hash_hmac('sha256', $sigData, $secretKey, true));

// Callback signature (includes Success and ApprovalCode)
$sigData = $shopId . $secretKey . $transactionId . $secretKey . $success . $secretKey . $approvalCode . $secretKey;
$signature = base64_encode(hash_hmac('sha256', $sigData, $secretKey, true));
```

### 6.2 Security Best Practices

1. **Never expose secret key** in client-side code
2. **Always validate callback signature** before updating orders
3. **Use HTTPS** for all API communications
4. **Implement idempotency** - check for duplicate transaction IDs
5. **Log all transactions** for audit trail

### 6.3 Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `FAHIPAY_SHOP_ID` | Yes | Your merchant Shop ID |
| `FAHIPAY_SECRET_KEY` | Yes | Your merchant secret key |
| `FAHIPAY_TEST_MODE` | No | Set to `true` for sandbox testing |
| `FAHIPAY_RETURN_URL` | Yes | URL for successful payment |
| `FAHIPAY_CANCEL_URL` | Yes | URL when user cancels |
| `FAHIPAY_ERROR_URL` | Yes | URL on payment error |

---

## 7. Troubleshooting

### Common Issues

#### "You cannot access this page directly"

**Cause**: Using GET request to payment page  
**Fix**: Use `getPaymentUrl()` method which POSTs data and follows redirect

#### "Parameters missing"

**Cause**: Missing or invalid signature  
**Fix**: Verify signature generation follows exact format

#### "Invalid signature"

**Cause**: Callback signature doesn't match  
**Fix**: Use `validateCallback()` method, ensure secret key is correct

---

## 8. Console Commands

```bash
# Install package (creates config file, migrations)
php artisan fahipay:install

# Create a test payment
php artisan fahipay:create {transaction_id} {amount} {--description=}

# Check payment status
php artisan fahipay:check {transaction_id}
```

---

## 9. Events

| Event | Fired When |
|-------|------------|
| `PaymentInitiatedEvent` | Payment created |
| `PaymentCompletedEvent` | Payment successful |
| `PaymentFailedEvent` | Payment failed |
| `PaymentPendingEvent` | Payment pending |
| `PaymentCancelledEvent` | Payment cancelled |

**Example Listener:**

```php
// In EventServiceProvider.php
protected $listen = [
    PaymentCompletedEvent::class => [
        SendOrderConfirmation::class,
    ],
];
```

---

## 10. Testing

To test in your Laravel app:

```php
// Test payment URL generation
$url = FahipayGateway::getPaymentUrl('TEST-123', 100.00);
echo $url;

// Test callback validation
$request = new Request([
    'Success' => 'true',
    'ShoppingCartID' => 'TEST-123',
    'ApprovalCode' => 'FP123',
    'Signature' => '...'
]);

if (FahipayGateway::validateCallback($request)) {
    // Process payment
}
```

---

*Document generated for fahipaydev/gateway-laravel*
*Version: Latest (commit cb10319)*