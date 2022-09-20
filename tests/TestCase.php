<?php

namespace Jhavenz\LaravelBatchUpdate\Tests;

use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\MigrationServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Providers\ComposerServiceProvider;
use Jhavenz\LaravelBatchUpdate\LaravelBatchUpdatesServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\LaravelRay\RayServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getApplicationProviders($app): array
    {
        return [
            DatabaseServiceProvider::class,
            MigrationServiceProvider::class,
            FilesystemServiceProvider::class,
            ComposerServiceProvider::class,
            RayServiceProvider::class,
            LaravelBatchUpdatesServiceProvider::class,
        ];
    }

    protected function resolveApplicationRateLimiting($app)
    {
        //
    }

    protected function setUpApplicationRoutes(): void
    {
        //
    }
}
