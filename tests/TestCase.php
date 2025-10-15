<?php

namespace Rcp\LaravelSearch\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rcp\LaravelSearch\RcpSearchServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            RcpSearchServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}