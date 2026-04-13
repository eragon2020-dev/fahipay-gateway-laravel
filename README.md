# FahiPay Gateway - Laravel Package

FahiPay Payment Gateway integration for Laravel 13. Accept payments from Maldives using FahiPay's secure payment infrastructure.

**API Endpoints:**
- Create Transaction: `POST https://fahipay.mv/api/merchants/createTxn/`
- Query Transaction: `GET https://fahipay.mv/api/merchants/getTxn/?mref=<TransactionID>`

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fahipay/gateway-laravel.svg)](https://packagist.org/packages/fahipay/gateway-laravel)
[![PHP Version](https://img.shields.io/packagist/php-v/fahipay/gateway-laravel.svg)](https://packagist.org/packages/fahipay/gateway-laravel)
[![License](https://img.shields.io/packagist/l/fahipay/gateway-laravel.svg)](https://packagist.org/packages/fahipay/gateway-laravel)

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [Basic Payment](#basic-payment)
  - [With Redirect](#with-redirect)
  - [Check Payment Status](#check-payment-status)
  - [Handle Callbacks](#handle-callbacks)
- [Database Integration](#database-integration)
- [Events](#events)
- [Facades](#facades)
- [Routes](#routes)
- [Views](#views)
- [Testing](#testing)
- [Examples](#examples)
  - [Staff Fee Payment](#example-1-staff-fee-payment)
  - [Subscription Payment](#example-2-subscription-payment)
  - [Event Registration](#example-3-event-registration)
- [Troubleshooting](#troubleshooting)

---

## Features

- Easy integration with FahiPay payment gateway
- Support for test mode
- Event-driven architecture
- Database model for payment tracking
- Built-in callback handling
- Artisan commands for testing
- Multiple payment methods
- Secure signature verification

---

## Requirements

- PHP 8.2+
- Laravel 13.0+
- PHP cURL extension
- PHP JSON extension

---

## Installation

### Step 1: Install via Composer

```bash
composer require fahipay/gateway-laravel
```

### Step 2: Run Installation Command

```bash
php artisan fahipay:install
```

This command will:
- Publish the configuration file (`config/fahipay.php`)
- Publish database migrations

### Step 3: Run Migrations

```bash
php artisan migrate
```

---

## Configuration

### Step 1: Configure Environment Variables

Add your FahiPay credentials to your `.env` file:

```env
# FahiPay Configuration
FAHIPAY_MERCHANT_ID=your_merchant_id
FAHIPAY_SECRET_KEY=your_secret_key

# Test Mode (set to false for production)
FAHIPAY_TEST_MODE=true

# URLs (optional - defaults are provided)
FAHIPAY_RETURN_URL=/fahipay/callback/success
FAHIPAY_CANCEL_URL=/fahipay/callback/cancel
FAHIPAY_ERROR_URL=/fahipay/callback/error
```

**To get your credentials:**
1. Visit https://fahipay.mv/merchants/portal/
2. Register or login to your merchant account
3. Get your Merchant ID and Secret Key

### Step 2: Verify Configuration

Check your `config/fahipay.php` after publishing:

```php
// config/fahipay.php
<?php

return [
    'merchant_id' => env('FAHIPAY_MERCHANT_ID', ''),
    'secret_key' => env('FAHIPAY_SECRET_KEY', ''),
    'test_mode' => env('FAHIPAY_TEST_MODE', false),
    // ... other config options
];
```

---

## Quick Start

### Create a Payment (5 Lines of Code!)

```php
use Fahipay\Gateway\Facades\FahipayGateway;

// Create payment and get redirect URL
$payment = FahipayGateway::createPayment('ORDER-001', 100.00);

// Redirect user to FahiPay
return redirect($payment->paymentUrl);
```

That's it! 🎉

---

## Usage

### Basic Payment

```php
use Fahipay\Gateway\Facades\FahipayGateway;

public function checkout(Request $request)
{
    // Generate unique transaction ID
    $transactionId = 'PAY-' . uniqid();
    
    // Create payment
    $payment = FahipayGateway::createPayment(
        transactionId: $transactionId,
        amount: $request->input('amount'),
        description: 'Order Payment'
    );
    
    // Redirect to payment page
    return redirect($payment->paymentUrl);
}
```

### With Redirect (Controller Method)

```php
public function initiatePayment(Request $request)
{
    $transactionId = 'ORDER-' . time();
    $amount = $request->amount;
    
    $payment = FahipayGateway::createPayment($transactionId, $amount);
    
    if ($payment->paymentUrl) {
        return redirect($payment->paymentUrl);
    }
    
    return back()->with('error', 'Failed to create payment');
}
```

### Check Payment Status

```php
use Fahipay\Gateway\Facades\FahipayGateway;

public function checkStatus(string $transactionId)
{
    $transaction = FahipayGateway::getTransaction($transactionId);
    
    if ($transaction) {
        // Transaction found
        return response()->json([
            'status' => $transaction->status->value,
            'amount' => $transaction->amount,
            'approval_code' => $transaction->approvalCode,
        ]);
    }
    
    return response()->json(['error' => 'Transaction not found'], 404);
}
```

### Handle Callbacks

The package provides built-in routes for callbacks. Just handle the events:

#### Option 1: Event Listeners (Recommended)

Create an event listener in your app:

```php
// app/Listeners/HandlePaymentCompleted.php

namespace App\Listeners;

use Fahipay\Gateway\Events\PaymentCompletedEvent;

class HandlePaymentCompleted
{
    public function handle(PaymentCompletedEvent $event)
    {
        // Find and update your order
        $order = Order::where('transaction_id', $event->transactionId)->first();
        
        if ($order) {
            $order->update([
                'payment_status' => 'paid',
                'approval_code' => $event->approvalCode,
            ]);
            
            // Send confirmation email, etc.
        }
    }
}
```

Register the listener in your `EventServiceProvider`:

```php
// app/Providers/EventServiceProvider.php

protected $listen = [
    \Fahipay\Gateway\Events\PaymentCompletedEvent::class => [
        \App\Listeners\HandlePaymentCompleted::class,
    ],
    \Fahipay\Gateway\Events\PaymentFailedEvent::class => [
        \App\Listeners\HandlePaymentFailed::class,
    ],
    \Fahipay\Gateway\Events\PaymentCancelledEvent::class => [
        \App\Listeners\HandlePaymentCancelled::class,
    ],
];
```

#### Option 2: Direct Route Handling

```php
// routes/web.php

Route::get('/payment/callback', function (\Illuminate\Http\Request $request) {
    $gateway = app(\Fahipay\Gateway\FahipayGateway::class);
    
    // Validate signature
    if (!$gateway->validateCallback($request)) {
        return response('Invalid signature', 403);
    }
    
    $success = $request->get('Success');
    $transactionId = $request->get('ShoppingCartID');
    $approvalCode = $request->get('ApprovalCode');
    
    if ($success === 'true') {
        // Update your order status
        Order::where('transaction_id', $transactionId)
            ->update(['status' => 'paid', 'approval_code' => $approvalCode]);
            
        return redirect('/payment/success');
    }
    
    return redirect('/payment/failed');
});
```

---

## Database Integration

### Using the Model

The package includes a `FahipayPayment` model:

```php
use Fahipay\Gateway\Models\FahipayPayment;

// Create payment record
$payment = FahipayPayment::createPayment(
    transactionId: 'ORDER-001',
    merchantId: 'merchant123',
    amount: 100.00,
    description: 'Test payment'
);

// Update status when completed
$payment->markAsCompleted('APPROVAL123');

// Or mark as failed
$payment->markAsFailed('Card declined');

// Query payments
$pendingPayments = FahipayPayment::pending()->get();
$completedPayments = FahipayPayment::completed()->get();
```

### Custom Database Table

If you have your own orders table, you can add a transaction_id column:

```php
Schema::table('orders', function (Blueprint $table) {
    $table->string('transaction_id')->nullable()->unique();
    $table->string('approval_code')->nullable();
    $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
});
```

---

## Events

The package dispatches the following events:

| Event | Description |
|-------|-------------|
| `PaymentInitiatedEvent` | When a payment is created |
| `PaymentCompletedEvent` | When payment is successful |
| `PaymentFailedEvent` | When payment fails |
| `PaymentCancelledEvent` | When user cancels payment |

### Example: Listening to Events

```php
// In a ServiceProvider's boot method
Event::listen(\Fahipay\Gateway\Events\PaymentCompletedEvent::class, function ($event) {
    Log::info("Payment completed: {$event->transactionId}");
    // Update your database, send notifications, etc.
});
```

---

## Facades

### Using Facade

```php
use Fahipay\Gateway\Facades\FahipayGateway;

// Create payment
$payment = FahipayGateway::createPayment('TXN-001', 50.00);

// Get payment URL
$url = FahipayGateway::getPaymentUrl('TXN-001', 50.00);

// Check status
$transaction = FahipayGateway::getTransaction('TXN-001');

// Validate callback
$isValid = FahipayGateway::validateCallback($request);

// Custom configuration
FahipayGateway::setTestMode(true)
    ->setReturnUrl('https://yoursite.com/payment/return')
    ->createPayment('TXN-002', 75.00);
```

### Using Dependency Injection

```php
use Fahipay\Gateway\FahipayGateway;

class PaymentController extends Controller
{
    public function __construct(
        protected FahipayGateway $fahipay
    ) {}

    public function create(Request $request)
    {
        $payment = $this->fahipay->createPayment(
            'TXN-' . time(),
            $request->amount
        );
        
        return redirect($payment->paymentUrl);
    }
}
```

---

## Routes

By default, the package automatically registers these routes:

| Route | Description |
|-------|-------------|
| `GET /fahipay/callback` | Main callback handler |
| `GET /fahipay/callback/success` | Success page |
| `GET /fahipay/callback/cancel` | Cancel page |
| `GET /fahipay/callback/error` | Error page |
| `POST /fahipay/webhook` | Webhook handler |

### Disable Default Routes

If you want to use your own routes, disable the default:

```php
// config/fahipay.php
'routes' => [
    'enabled' => false,
],
```

Then create your own:

```php
Route::post('/payment/fahipay/webhook', [PaymentController::class, 'webhook']);
```

---

## Views

The package provides default views in `resources/views/fahipay/`:

- `success.blade.php` - Payment success page
- `error.blade.php` - Payment error page
- `cancelled.blade.php` - Payment cancelled page

### Publish Views

```bash
php artisan vendor:publish --tag=fahipay-views
```

### Custom Views

Create your own views and use them:

```php
// In your controller
return view('payments.fahipay.success', [
    'transactionId' => $transactionId,
    'approvalCode' => $approvalCode,
]);
```

---

## Testing

### Using Artisan Commands

```bash
# Create a test payment
php artisan fahipay:create TEST-001 100.00 --description="Test payment"

# Check payment status
php artisan fahipay:check TEST-001
```

### Unit Testing

```php
<?php

use Fahipay\Gateway\Facades\FahipayGateway;

test('can create payment', function () {
    FahipayGateway::setTestMode(true);
    
    $payment = FahipayGateway::createPayment('TEST-001', 100.00);
    
    expect($payment->transactionId)->toBe('TEST-001');
    expect($payment->amount)->toBe(100.00);
    expect($payment->status->value)->toBe('pending');
});

test('can validate signature', function () {
    $gateway = app(FahipayGateway::class);
    
    $signature = $gateway->generateSignature('TXN-001', 100.00, '2024-01-01 12:00:00');
    
    expect($signature)->toBeString();
});
```

---

## Examples

### Example 1: Staff Fee Payment

This is a complete example for a staff monthly fee portal.

#### Step 1: Create Migration

```bash
php artisan make:migration create_staff_fees_table
```

```php
// database/migrations/xxxx_xx_xx_create_staff_fees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_fees', function (Blueprint $table) {
            $table->id();
            $table->string('staff_id');
            $table->string('staff_name');
            $table->decimal('amount', 10, 2);
            $table->string('month'); // e.g., "April 2026"
            $table->string('transaction_id')->nullable()->unique();
            $table->string('approval_code')->nullable();
            $table->enum('status', ['unpaid', 'paid', 'failed'])->default('unpaid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_fees');
    }
};
```

#### Step 2: Create Model

```php
// app/Models/StaffFee.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffFee extends Model
{
    protected $fillable = [
        'staff_id',
        'staff_name',
        'amount',
        'month',
        'transaction_id',
        'approval_code',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}
```

#### Step 3: Create Controller

```php
// app/Http/Controllers/StaffFeeController.php

namespace App\Http\Controllers;

use App\Models\StaffFee;
use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffFeeController extends Controller
{
    public function index()
    {
        $fees = StaffFee::where('staff_id', auth()->id())->get();
        return view('staff.fees.index', compact('fees'));
    }

    public function pay(StaffFee $fee)
    {
        // Generate unique transaction ID
        $transactionId = 'FEE-' . $fee->id . '-' . Str::random(8);
        
        // Update fee with transaction ID
        $fee->update(['transaction_id' => $transactionId]);
        
        // Create FahiPay payment
        $payment = FahipayGateway::createPayment(
            transactionId: $transactionId,
            amount: $fee->amount,
            description: "Monthly fee for {$fee->month}"
        );
        
        // Redirect to FahiPay
        return redirect($payment->paymentUrl);
    }

    public function callback(Request $request)
    {
        // Validate callback
        $gateway = app(\Fahipay\Gateway\FahipayGateway::class);
        
        if (!$gateway->validateCallback($request)) {
            return response('Invalid signature', 403);
        }
        
        $transactionId = $request->get('ShoppingCartID');
        $success = $request->get('Success') === 'true';
        $approvalCode = $request->get('ApprovalCode');
        
        // Find the fee
        $fee = StaffFee::where('transaction_id', $transactionId)->first();
        
        if (!$fee) {
            return response('Transaction not found', 404);
        }
        
        // Update status
        if ($success) {
            $fee->update([
                'status' => 'paid',
                'approval_code' => $approvalCode,
            ]);
            return redirect('/staff/fees?status=success');
        }
        
        $fee->update(['status' => 'failed']);
        return redirect('/staff/fees?status=failed');
    }
}
```

#### Step 4: Add Routes

```php
// routes/web.php

use App\Http\Controllers\StaffFeeController;

Route::middleware(['auth'])->group(function () {
    Route::get('/staff/fees', [StaffFeeController::class, 'index'])->name('staff.fees');
    Route::post('/staff/fees/{fee}/pay', [StaffFeeController::class, 'pay'])->name('staff.fees.pay');
    Route::get('/staff/fees/callback', [StaffFeeController::class, 'callback'])->name('staff.fees.callback');
});
```

#### Step 5: Create View

```html
<!-- resources/views/staff/fees/index.blade.php -->

@extends('layouts.app')

@section('content')
<h1>My Monthly Fees</h1>

@if(session('status') === 'success')
    <div class="alert alert-success">Payment successful!</div>
@elseif(session('status') === 'failed')
    <div class="alert alert-danger">Payment failed. Please try again.</div>
@endif

<table class="table">
    <thead>
        <tr>
            <th>Month</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach($fees as $fee)
        <tr>
            <td>{{ $fee->month }}</td>
            <td>MVR {{ number_format($fee->amount, 2) }}</td>
            <td>
                <span class="badge bg-{{ $fee->status === 'paid' ? 'success' : 'warning' }}">
                    {{ ucfirst($fee->status) }}
                </span>
            </td>
            <td>
                @if($fee->status === 'unpaid')
                    <form action="{{ route('staff.fees.pay', $fee) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">Pay Now</button>
                    </form>
                @else
                    <button class="btn btn-secondary" disabled>Paid</button>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
```

---

### Example 2: Subscription Payment

```php
// app/Http/Controllers/SubscriptionController.php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $plan = $request->input('plan');
        $amount = match ($plan) {
            'basic' => 100,
            'premium' => 250,
            default => 100,
        };
        
        $transactionId = 'SUB-' . auth()->id() . '-' . time();
        
        // Create subscription record
        $subscription = Subscription::create([
            'user_id' => auth()->id(),
            'plan' => $plan,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'status' => 'pending',
        ]);
        
        // Create payment
        $payment = FahipayGateway::createPayment(
            transactionId: $transactionId,
            amount: $amount,
            description: "Subscription - {$plan} plan"
        );
        
        return redirect($payment->paymentUrl);
    }

    public function handleCallback(Request $request)
    {
        $gateway = app(\Fahipay\Gateway\FahipayGateway::class);
        
        if (!$gateway->validateCallback($request)) {
            return response('Invalid signature', 403);
        }
        
        $transactionId = $request->get('ShoppingCartID');
        $success = $request->get('Success') === 'true';
        
        $subscription = Subscription::where('transaction_id', $transactionId)->first();
        
        if ($success && $subscription) {
            $subscription->update([
                'status' => 'active',
                'approval_code' => $request->get('ApprovalCode'),
                'started_at' => now(),
            ]);
            
            return redirect('/subscription/success');
        }
        
        $subscription?->update(['status' => 'failed']);
        return redirect('/subscription/failed');
    }
}
```

---

### Example 3: Event Registration Payment

```php
// app/Http/Controllers/EventRegistrationController.php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventRegistrationController extends Controller
{
    public function register(Request $request)
    {
        $event = Event::findOrFail($request->event_id);
        
        $transactionId = 'EVT-' . $event->id . '-' . auth()->id() . '-' . time();
        
        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'transaction_id' => $transactionId,
            'amount' => $event->price,
            'status' => 'pending',
        ]);
        
        $payment = FahipayGateway::createPayment(
            transactionId: $transactionId,
            amount: $event->price,
            description: "Event: {$event->title}"
        );
        
        return redirect($payment->paymentUrl);
    }

    public function callback(Request $request)
    {
        $gateway = app(\Fahipay\Gateway\FahipayGateway::class);
        
        if (!$gateway->validateCallback($request)) {
            return response('Invalid signature', 403);
        }
        
        $transactionId = $request->get('ShoppingCartID');
        $success = $request->get('Success') === 'true';
        
        $registration = EventRegistration::where('transaction_id', $transactionId)->first();
        
        if ($success && $registration) {
            $registration->update([
                'status' => 'confirmed',
                'approval_code' => $request->get('ApprovalCode'),
            ]);
            
            // Send confirmation email
            // Mail::to($registration->user)->send(new RegistrationConfirmed($registration));
        } else {
            $registration?->update(['status' => 'cancelled']);
        }
        
        return redirect('/events/registration/success');
    }
}
```

---

## Troubleshooting

### Common Issues

#### 1. "Merchant ID and Secret Key are required"

```php
// Check your configuration
dump(config('fahipay.merchant_id'));
dump(config('fahipay.secret_key'));

// Make sure .env is loaded
php artisan config:clear
php artisan cache:clear
```

#### 2. Signature Validation Failed

```php
// Check your secret key
$gateway = app(\Fahipay\Gateway\FahipayGateway::class);
$signature = $gateway->generateSignature($transactionId, $amount, $timestamp);

// Verify it matches what FahiPay expects
```

#### 3. Payment Link Returns 404

```php
// Make sure you're using the correct test mode URL
// Test mode: https://test.fahipay.mv/api/merchants
// Production: https://fahipay.mv/api/merchants
```

#### 4. Callback Not Received

```php
// Check your logs
tail -f storage/logs/laravel.log

// Verify your return URL is accessible from the internet
// (FahiPay servers need to reach your callback URL)
```

#### 5. Database Connection Error

```php
// Check database configuration
php artisan tinker
DB::connection()->getPdo();
```

### Debug Mode

Enable detailed logging:

```php
// .env
FAHIPAY_LOGGING=true
FAHIPAY_LOG_CHANNEL=stack
```

### Test Your Integration

```bash
# Create test payment
php artisan fahipay:create TEST-001 10.00

# Check its status
php artisan fahipay:check TEST-001
```

---

## Security

- Always validate callback signatures
- Never trust the `Success` parameter alone
- Store transaction IDs in your database
- Use HTTPS in production
- Keep your secret key secure
- Log all payment attempts

---

## Advanced Configuration

### Custom Timeout

```php
// config/fahipay.php
'timeout' => 60, // seconds
```

### Multiple Merchant Accounts

```php
// Using different merchant for different payments
$gateway = new \Fahipay\Gateway\FahipayGateway();
$gateway->setMerchantId('merchant2')
       ->setSecretKey('secret2')
       ->setTestMode(true)
       ->createPayment('TXN-002', 50.00);
```

### Custom API Endpoints

```php
// config/fahipay.php
'base_url' => 'https://custom-api.example.com',
'test_mode_url' => 'https://custom-test-api.example.com',
```

---

## License

MIT License. See [LICENSE](LICENSE) for more information.

---

## Support

- Documentation: https://fahipay.mv/merchants/portal/
- Issues: https://github.com/fahipay/gateway-laravel/issues

---

## Changelog

### 1.0.0
- Initial release
- Basic payment functionality
- Event-driven architecture
- Database model included
- Artisan commands
- Test mode support# fahipay-gateway-laravel
