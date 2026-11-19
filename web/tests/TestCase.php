<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.api_key' => '']);
        Http::preventStrayRequests();
    }

    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        // Tests use explicit PHPUnit/container settings, never local environment files.
        $app->useEnvironmentPath(__DIR__)->loadEnvironmentFrom('testing-no-environment');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
