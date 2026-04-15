<?php

use Fahipay\Gateway\Http\Controllers\Api\PaymentController;
use Fahipay\Gateway\Http\Controllers\WebhookController;
use Fahipay\Gateway\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index']);
    Route::post('/', [PaymentController::class, 'store']);
    Route::get('/{transactionId}', [PaymentController::class, 'show']);
    Route::get('/{transactionId}/verify', [PaymentController::class, 'verify']);
    Route::patch('/{transactionId}', [PaymentController::class, 'update']);
    Route::delete('/{transactionId}', [PaymentController::class, 'destroy']);
});

Route::post('/webhook', [WebhookController::class, 'handle'])
    ->middleware([VerifyWebhookSignature::class])
    ->name('fahipay.api.webhook');

Route::post('/fahipay/webhook', [WebhookController::class, 'handle'])
    ->middleware([VerifyWebhookSignature::class])
    ->name('fahipay.webhook');