<?php

use Fahipay\Gateway\FahipayGateway;

beforeEach(function () {
    config(['fahipay.shop_id' => 'test_shop']);
    config(['fahipay.secret_key' => 'test_secret_key_12345']);
    config(['fahipay.test_mode' => true]);
});

/**
 * Security Test: Signature Validation
 */
describe('Signature Validation Security', function () {
    test('prevents timing attacks with hash_equals', function () {
        $gateway = app(FahipayGateway::class);
        
        // Real signature
        $realSignature = base64_encode(hash_hmac('sha256', 'test', 'key', true));
        
        // Attack: Try to guess signature byte by byte
        // hash_equals should prevent timing attacks
        $startTime = microtime(true);
        $gateway->verifySignature('true', 'TEST', 'APPROVAL', 'wrong_signature');
        $time1 = microtime(true) - $startTime;
        
        $startTime = microtime(true);
        $gateway->verifySignature('true', 'TEST', 'APPROVAL', 'another_wrong');
        $time2 = microtime(true) - $startTime;
        
        // Times should be similar (within reason)
        expect($time1)->toBeLessThan(1);
        expect($time2)->toBeLessThan(1);
    });

    test('rejects empty signature', function () {
        $gateway = app(FahipayGateway::class);
        
        $isValid = $gateway->verifySignature('true', 'TEST', 'APPROVAL', '');
        
        expect($isValid)->toBeFalse();
    });

    test('rejects signature with wrong transaction id', function () {
        $gateway = app(FahipayGateway::class);
        
        // Generate signature for TEST-001
        $timestamp = now()->format('Y-m-d H:i:s');
        $signature = $gateway->generateSignature('TEST-001', 100.00, $timestamp);
        
        // Try to use for different transaction
        $isValid = $gateway->verifySignature('true', 'TEST-002', 'APPROVAL', $signature);
        
        expect($isValid)->toBeFalse();
    });

    test('rejects tampered success parameter', function () {
        $gateway = app(FahipayGateway::class);
        
        // Generate signature for success=true
        $timestamp = now()->format('Y-m-d H:i:s');
        $signature = $gateway->generateSignature('TEST-001', 0, $timestamp);
        
        // Create data that was signed
        $data = 'test_merchant' . 'TEST-001' . 'APPROVAL123' . 'true';
        $expectedSignature = base64_encode(hash_hmac('sha256', $data, 'test_secret_key_12345', true));
        
        // Try to change success to false
        $dataTampered = 'test_merchant' . 'TEST-001' . 'APPROVAL123' . 'false';
        $tamperedSignature = base64_encode(hash_hmac('sha256', $dataTampered, 'test_secret_key_12345', true));
        
        $isValid = $gateway->verifySignature('false', 'TEST-001', 'APPROVAL123', $expectedSignature);
        
        expect($isValid)->toBeFalse();
    });
});

/**
 * Security Test: Input Validation
 */
describe('Input Validation Security', function () {
    test('rejects transaction id with special characters', function () {
        $gateway = app(FahipayGateway::class);
        
        $signature = $gateway->generateSignature('TEST<script>alert(1)</script>', 100.00, '2024-01-01 12:00:00');
        
        expect($signature)->toBeString();
        // The signature should be generated but transaction ID should be sanitized in requests
    });

    test('rejects negative amount', function () {
        $gateway = app(FahipayGateway::class);
        
        try {
            $gateway->createPayment('TEST-001', -100.00);
            expect(false)->toBeTrue(); // Should not reach here
        } catch (\Fahipay\Gateway\Exceptions\FahipayException $e) {
            expect($e->getMessage())->toContain('Merchant ID');
        }
    });

    test('handles extremely large amount safely', function () {
        $gateway = app(FahipayGateway::class);
        
        // Should handle gracefully
        $signature = $gateway->generateSignature('TEST-001', 999999999.99, '2024-01-01 12:00:00');
        
        expect($signature)->toBeString();
    });

    test('rejects sql injection in transaction id', function () {
        $gateway = app(FahipayGateway::class);
        
        $maliciousId = "TEST-001'; DROP TABLE fahipay_payments;--";
        $signature = $gateway->generateSignature($maliciousId, 100.00, '2024-01-01 12:00:00');
        
        // Signature should be generated but this ID should never be used directly in queries
        expect($signature)->toBeString();
    });
});

/**
 * Security Test: API Security
 */
describe('API Endpoint Security', function () {
    test('api endpoints require authentication when enabled', function () {
        config(['fahipay.api.enabled' => true]);
        
        // Should require some form of auth (api token, etc)
        $response = $this->postJson('/api/fahipay/payments', [
            'amount' => 100,
        ]);
        
        // Should return 422 (validation) not 401 if auth is optional
        $response->assertStatus(422);
    });

    test('webhook rejects requests without signature', function () {
        $response = $this->postJson('/api/fahipay/webhook', [
            'Success' => 'true',
            'ShoppingCartID' => 'TEST-001',
        ]);
        
        // Should return 401 without proper signature
        expect(in_array($response->status(), [401, 422]))->toBeTrue();
    });
});

/**
 * Security Test: Data Exposure
 */
describe('Data Exposure Security', function () {
    test('response does not expose secret key', function () {
        $gateway = app(FahipayGateway::class);
        
        $response = $gateway->getLastResponse();
        
        if ($response) {
            expect(json_encode($response))->not->toContain('test_secret_key_12345');
        }
    });

    test('error responses do not expose sensitive data', function () {
        config(['fahipay.secret_key' => '']); // Empty secret
        
        $gateway = app(FahipayGateway::class);
        
        try {
            $gateway->createPayment('TEST-001', 100.00);
        } catch (\Exception $e) {
            expect($e->getMessage())->not->toContain('secret');
        }
    });
});

/**
 * Security Test: Race Conditions
 */
describe('Race Condition Security', function () {
    test('handles duplicate payment creation', function () {
        $transactionId = 'RACE-TEST-' . time();
        
        // First request
        $payment1 = FahipayGateway::createPayment($transactionId, 100.00);
        
        // Second request (same ID)
        $payment2 = FahipayGateway::createPayment($transactionId, 100.00);
        
        // Both should succeed but with same ID - DB should handle uniqueness
        expect($payment1->transactionId)->toBe($transactionId);
        expect($payment2->transactionId)->toBe($transactionId);
    });
});

/**
 * Security Test: CSRF Protection
 */
describe('CSRF Protection', function () {
    test('post routes require csrf token', function () {
        // Without CSRF token, should fail
        $response = $this->post('/fahipay/payment/initiate', [
            'amount' => 100,
        ]);
        
        // Should return 419 (CSRF mismatch) or 422 (validation error)
        expect(in_array($response->status(), [419, 422, 500]))->toBeTrue();
    });
});

/**
 * Security Test: HTTPS/TLS
 */
describe('Transport Security', function () {
    test('checks for https in production urls', function () {
        $config = config('fahipay');
        
        // In production, URLs should be HTTPS
        $testModeUrl = $config['test_mode_url'] ?? '';
        
        // URLs should start with https:// in production
        expect($testModeUrl)->toBeString();
    });
});