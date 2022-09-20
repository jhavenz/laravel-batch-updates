<?php

use Illuminate\Database\Eloquent\Model;
use Jhavenz\LaravelBatchUpdate\Tests\Fixtures\Post;
use Jhavenz\LaravelBatchUpdate\Tests\Fixtures\TypeCircus;
use Jhavenz\LaravelBatchUpdate\Tests\Fixtures\User;

it('can create instances from factory', function () {
    foreach (
        [
            User::factory()->create(),
            Post::factory()->create(),
            TypeCircus::factory()->create(),
        ] as $model
    ) {
        expect($model)
            ->toBeInstanceOf(Model::class)
            ->and($model->toArray())
            ->not
            ->toBeEmpty();
    }
});
