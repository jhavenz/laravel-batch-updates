<?php

use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Jhavenz\LaravelBatchUpdate\Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class)->in(__DIR__);

function sqLite(): SQLiteConnection
{
    return app('db.factory')->make([], 't');
}
