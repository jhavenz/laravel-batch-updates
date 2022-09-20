<?php

namespace Jhavenz\LaravelBatchUpdate;

use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;

class LaravelBatchUpdatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (Env::get('__PACKAGE_TESTING__')) {
            $this->loadMigrationsFrom(dirname(__DIR__).'/database');
        }
    }
}
