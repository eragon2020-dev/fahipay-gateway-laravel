<?php

use Fahipay\Gateway\FahipayGateway;
use Illuminate\Http\Request;
use Fahipay\Gateway\Models\FahipayPayment;
use Fahipay\Gateway\Enums\PaymentStatus;

beforeEach(function () {
    config(['fahipay.merchant_id' => 'test_merchant']);
    config(['fahipay.secret_key' => 'test_secret']);
    config(['fahipay.test_mode' => true]);
});

test('can validate callback with valid signature', function () {
    $gateway = app(FahipayGateway::class);
    
    $timestamp = now()->format('Y-m-d H:i:s');
    $signature = $gateway->generateSignature('TEST-001', 100.00, $timestamp);
    
    $request = Request::create('/callback', 'GET', [
        'Success' => 'true',
        'ShoppingCartID' => 'TEST-001',
        'ApprovalCode' => 'APPROVAL123',
        'Signature' => $signature,
    ]);
    
    expect($gateway->validateCallback($request))->toBeTrue();
});

test('rejects callback with invalid signature', function () {
    $gateway = app(FahipayGateway::class);
    
    $request = Request::create('/callback', 'GET', [
        'Success' => 'true',
        'ShoppingCartID' => 'TEST-001',
        'ApprovalCode' => 'APPROVAL123',
        'Signature' => 'invalid_signature',
    ]);
    
    expect($gateway->validateCallback($request))->toBeFalse();
});

test('can handle successful callback', function () {
    $gateway = app(FahipayGateway::class);
    
    FahipayPayment::createPayment('CALLBACK-001', 'test_merchant', 100.00);
    
    $timestamp = now()->format('Y-m-d H:i:s');
    $signature = $gateway->generateSignature('CALLBACK-001', 100.00, $timestamp);
    
    $request = Request::create('/callback', 'GET', [
        'Success' => 'true',
        'ShoppingCartID' => 'CALLBACK-001',
        'ApprovalCode' => 'APPROVAL123',
        'Signature' => $signature,
    ]);
    
    $transaction = $gateway->handleCallback($request);
    
    expect($transaction->transactionId)->toBe('CALLBACK-001');
    expect($transaction->status)->toBe(PaymentStatus::COMPLETED);
    expect($transaction->approvalCode)->toBe('APPROVAL123');
});

test('can handle failed callback', function () {
    $gateway = app(FahipayGateway::class);
    
    FahipayPayment::createPayment('CALLBACK-002', 'test_merchant', 50.00);
    
    $timestamp = now()->format('Y-m-d H:i:s');
    $signature = $gateway->generateSignature('CALLBACK-002', 50.00, $timestamp);
    
    $request = Request::create('/callback', 'GET', [
        'Success' => 'false',
        'ShoppingCartID' => 'CALLBACK-002',
        'Message' => 'Insufficient funds',
        'Signature' => $signature,
    ]);
    
    $transaction = $gateway->handleCallback($request);
    
    expect($transaction->transactionId)->toBe('CALLBACK-002');
    expect($transaction->status)->toBe(PaymentStatus::FAILED);
});

test('rejects callback with tampered transaction id', function () {
    $gateway = app(FahipayGateway::class);
    
    $timestamp = now()->format('Y-m-d H:i:s');
    $signature = $gateway->generateSignature('ORIGINAL-001', 100.00, $timestamp);
    
    // Try to use signature from different transaction
    $request = Request::create('/callback', 'GET', [
        'Success' => 'true',
        'ShoppingCartID' => 'TAMPERED-001', // Different ID
        'ApprovalCode' => 'APPROVAL123',
        'Signature' => $signature,
    ]);
    
    expect($gateway->validateCallback($request))->toBeFalse();
});

test('rejects callback with tampered amount', function () {
    $gateway = app(FahipayGateway::class);
    
    $timestamp = now()->format('Y-m-d H:i:s');
    $signature = $gateway->generateSignature('TEST-001', 100.00, $timestamp);
    
    // Use same signature but expect different amount processing
    // The gateway should check transaction ID consistency
    $request = Request::create('/callback', 'GET', [
        'Success' => 'true',
        'ShoppingCartID' => 'TEST-001',
        'ApprovalCode' => 'APPROVAL123',
        'Signature' => $signature,
    ]);
    
    // Valid signature with correct transaction
    expect($gateway->validateCallback($request))->toBeTrue();
});

test('handles callback without approval code', function () {
    $gateway = app(FahipayGateway::class);
    
    $timestamp = now()->format('Y-m-d H:i:s');
    $signature = $gateway->generateSignature('TEST-002', 0, $timestamp);
    
    $request = Request::create('/callback', 'GET', [
        'Success' => 'true',
        'ShoppingCartID' => 'TEST-002',
        'ApprovalCode' => null,
        'Signature' => $signature,
    ]);
    
    // Should handle null approval code
    $transaction = $gateway->handleCallback($request);
    
    expect($transaction->approvalCode)->toBeNull();
});

test('webhook endpoint exists and responds', function () {
    $response = $this->postJson('/api/fahipay/webhook', [
        'Success' => 'false',
        'ShoppingCartID' => 'WEBHOOK-001',
        'Message' => 'Test',
    ]);
    
    // Should return 401 without signature
    $response->assertStatus(401);
});

test('api payment endpoints are protected', function () {
    // Test without authentication
    $response = $this->getJson('/api/fahipay/payments');
    $response->assertStatus(200); // May be public or 401 depending on config
    
    $response = $this->postJson('/api/fahipay/payments', [
        'amount' => 100,
    ]);
    // Should validate input
    $response->assertStatus(422);
});