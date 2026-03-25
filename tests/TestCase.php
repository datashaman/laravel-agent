<?php

namespace DataShaman\LaravelAgent\Tests;

use DataShaman\LaravelAgent\LaravelAgentServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAgentServiceProvider::class,
        ];
    }
}
