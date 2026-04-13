<?php

use Orchestra\Testbench\TestCase;
use Fahipay\Gateway\FahipayGateway;

abstract class TestCaseBase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Fahipay\Gateway\FahipayGatewayServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'FahipayGateway' => \Fahipay\Gateway\Facades\FahipayGateway::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('fahipay', [
            'shop_id' => 'test_shop',
            'secret_key' => 'test_secret_key',
            'test_mode' => true,
            'return_url' => 'http://localhost/callback',
            'cancel_url' => 'http://localhost/cancel',
            'error_url' => 'http://localhost/error',
            'base_url' => 'https://test.fahipay.mv/api/merchants',
            'web_url' => 'https://test.fahipay.mv',
        ]);
    }
}