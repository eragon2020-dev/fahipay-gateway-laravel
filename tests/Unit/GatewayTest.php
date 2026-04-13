<?php

use Fahipay\Gateway\Facades\FahipayGateway;
use Fahipay\Gateway\FahipayGateway as Gateway;

beforeEach(function () {
    config(['fahipay.merchant_id' => 'test_merchant']);
    config(['fahipay.secret_key' => 'test_secret']);
    config(['fahipay.test_mode' => true]);
});

test('gateway can be instantiated', function () {
    $gateway = app(FahipayGateway::class);
    
    expect($gateway)->toBeInstanceOf(Gateway::class);
});

test('gateway checks if configured', function () {
    $gateway = app(FahipayGateway::class);
    
    expect($gateway->isConfigured())->toBeTrue();
});

test('gateway gets merchant id', function () {
    $gateway = app(FahipayGateway::class);
    
    expect($gateway->getMerchantId())->toBe('test_merchant');
});

test('gateway can set custom merchant id', function () {
    $gateway = app(FahipayGateway::class);
    $gateway->setMerchantId('custom_merchant');
    
    expect($gateway->getMerchantId())->toBe('custom_merchant');
});

test('gateway can toggle test mode', function () {
    $gateway = app(FahipayGateway::class);
    
    expect($gateway->isTestMode())->toBeTrue();
    
    $gateway->setTestMode(false);
    
    expect($gateway->isTestMode())->toBeFalse();
});

test('gateway generates valid signature', function () {
    $gateway = app(FahipayGateway::class);
    
    $signature = $gateway->generateSignature(
        'TEST-001',
        100.00,
        '2024-01-01 12:00:00'
    );
    
    expect($signature)->toBeString()
        ->not->toBeEmpty();
});

test('gateway verifies signature correctly', function () {
    $gateway = app(FahipayGateway::class);
    
    $signature = $gateway->generateSignature(
        'TEST-001',
        100.00,
        '2024-01-01 12:00:00'
    );
    
    $isValid = $gateway->verifySignature(
        'true',
        'TEST-001',
        'APPROVAL123',
        $signature
    );
    
    expect($isValid)->toBeTrue();
});

test('gateway detects invalid signature', function () {
    $gateway = app(FahipayGateway::class);
    
    $isValid = $gateway->verifySignature(
        'true',
        'TEST-001',
        'APPROVAL123',
        'invalid_signature'
    );
    
    expect($isValid)->toBeFalse();
});

test('gateway can set return url', function () {
    $gateway = app(FahipayGateway::class);
    $gateway->setReturnUrl('https://example.com/return');
    
    $config = $gateway->getConfig();
    
    expect($config['return_url'])->toBe('https://example.com/return');
});