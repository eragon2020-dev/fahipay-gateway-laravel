# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within this package, please send an e-mail to support@fahipay.mv. All security vulnerabilities will be promptly addressed.

## Security Features Implemented

### 1. Signature Verification
- HMAC-SHA256 signatures for all API requests
- Timing-attack safe comparison using `hash_equals()`
- Callback signature validation before processing

### 2. Input Validation
- Transaction ID format validation (alphanumeric, dashes, underscores only)
- Amount validation (min/max limits)
- URL validation for callback endpoints
- SQL injection prevention via parameterized queries

### 3. CSRF Protection
- Laravel's built-in CSRF protection for web routes
- Form token verification for payment initiation

### 4. Rate Limiting
- Laravel's default throttle middleware available
- Configurable API rate limiting

### 5. Secure Configuration
- No sensitive data in error responses
- Environment-based credential storage
- Secret key never exposed in logs or responses

## Security Best Practices

### For Integration

1. **Always verify callbacks**
```php
// Never trust Success parameter alone
if (!$gateway->validateCallback($request)) {
    return response('Invalid signature', 403);
}
```

2. **Use HTTPS in production**
```php
// In .env
FAHIPAY_BASE_URL=https://api.fahipay.mv
FAHIPAY_WEB_URL=https://fahipay.mv
```

3. **Store transaction IDs**
```php
// Always record transactions in your database
$payment = FahipayPayment::createPayment(
    $transactionId,
    $merchantId,
    $amount
);
```

4. **Handle events properly**
```php
// Listen to events for reliable processing
Event::listen(PaymentCompletedEvent::class, function ($event) {
    Order::where('transaction_id', $event->transactionId)
        ->update(['status' => 'paid']);
});
```

5. **Implement idempotency**
```php
// Check if transaction already processed
if ($existingPayment->status === PaymentStatus::COMPLETED) {
    return response()->json(['status' => 'already_processed']);
}
```

## Known Considerations

### Timing Attacks
- Signature verification uses `hash_equals()` to prevent timing attacks

### Race Conditions
- Database unique constraints on transaction_id
- Idempotent payment processing recommended

### Data Sanitization
- Transaction IDs are validated against a strict pattern
- All user input is sanitized before processing

## Changelog

### 1.0.x
- Added signature validation
- Added input sanitization
- Added CSRF protection
- Added rate limiting options
- Added webhook signature verification middleware