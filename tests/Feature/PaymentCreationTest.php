<?php

use Fahipay\Gateway\Facades\FahipayGateway;
use Fahipay\Gateway\Models\FahipayPayment;
use Fahipay\Gateway\Enums\PaymentStatus;

beforeEach(function () {
    config(['fahipay.merchant_id' => 'test_merchant']);
    config(['fahipay.secret_key' => 'test_secret']);
    config(['fahipay.test_mode' => true]);
    config(['fahipay.return_url' => 'http://localhost/return']);
    config(['fahipay.cancel_url' => 'http://localhost/cancel']);
    config(['fahipay.error_url' => 'http://localhost/error']);
});

test('can create payment via facade', function () {
    $payment = FahipayGateway::createPayment('TEST-001', 100.00, 'Test payment');

    expect($payment->transactionId)->toBe('TEST-001');
    expect($payment->amount)->toBe(100.00);
    expect($payment->status)->toBe(PaymentStatus::PENDING);
});

test('can generate valid signature', function () {
    $signature = FahipayGateway::generateSignature('TEST-001', 100.00, '2024-01-01 12:00:00');

    expect($signature)->toBeString()
        ->not->toBeEmpty();
});

test('can verify valid signature', function () {
    $gateway = app(\Fahipay\Gateway\FahipayGateway::class);
    $signature = $gateway->generateSignature('TEST-001', 100.00, '2024-01-01 12:00:00');

    $isValid = $gateway->verifySignature('true', 'TEST-001', 'APPROVAL123', $signature);

    expect($isValid)->toBeTrue();
});

test('detects invalid signature', function () {
    $isValid = FahipayGateway::verifySignature('true', 'TEST-001', 'APPROVAL123', 'invalid_signature');

    expect($isValid)->toBeFalse();
});

test('checks if gateway is configured', function () {
    expect(FahipayGateway::isConfigured())->toBeTrue();
});

test('checks test mode status', function () {
    expect(FahipayGateway::isTestMode())->toBeTrue();

    FahipayGateway::setTestMode(false);

    expect(FahipayGateway::isTestMode())->toBeFalse();
});

test('can set custom return url', function () {
    $gateway = app(\Fahipay\Gateway\FahipayGateway::class);
    $gateway->setReturnUrl('https://custom.com/return');

    // Config should be updated
    expect(config('fahipay.return_url'))->toBe('https://custom.com/return');
});

test('can set custom merchant credentials', function () {
    FahipayGateway::setMerchantId('custom_merchant')
        ->setSecretKey('custom_secret');

    expect(FahipayGateway::getMerchantId())->toBe('custom_merchant');
});

test('fahipay payment model can be created', function () {
    $payment = FahipayPayment::createPayment(
        'TEST-001',
        'test_merchant',
        100.00,
        'Test payment'
    );

    expect($payment->transaction_id)->toBe('TEST-001');
    expect($payment->amount)->toBe(100.00);
    expect($payment->status)->toBe(PaymentStatus::PENDING);
});

test('fahipay payment model can mark as completed', function () {
    $payment = FahipayPayment::createPayment(
        'TEST-002',
        'test_merchant',
        50.00
    );

    $payment->markAsCompleted('APPROVAL123');

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED);
    expect($payment->fresh()->approval_code)->toBe('APPROVAL123');
});

test('fahipay payment model can mark as failed', function () {
    $payment = FahipayPayment::createPayment(
        'TEST-003',
        'test_merchant',
        75.00
    );

    $payment->markAsFailed('Card declined');

    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED);
    expect($payment->fresh()->error_message)->toBe('Card declined');
});

test('payment status enum works correctly', function () {
    expect(PaymentStatus::PENDING->label())->toBe('Pending');
    expect(PaymentStatus::COMPLETED->isSuccessful())->toBeTrue();
    expect(PaymentStatus::FAILED->isFailed())->toBeTrue();
    expect(PaymentStatus::PENDING->isPending())->toBeTrue();
});

test('can get payment url', function () {
    $url = FahipayGateway::getPaymentUrl('TEST-001', 100.00);

    expect($url)->toBeString()
        ->toContain('TEST-001')
        ->toContain('100.00');
});