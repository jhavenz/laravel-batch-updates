<?php

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Jhavenz\LaravelBatchUpdate\BatchedUpdate;
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

it('can compile a batched update query for a model with timestamps', function () {
    $users = User::factory()->count(5)->create();
    $batchedUpdate = BatchedUpdate::createFromModel(User::class);

    expect($batchedUpdate->compileUpdateQuery($users)->getCompiledQuery())
        ->toBe(createCompiledQueryExpectation($users));
});

// TODO
it('can compile a batched update query for a model without timestamps');
it('can compile a batched update query for a model with class castable attribute');
it('can compile a batched update query for a model with native eloquent castable attribute');

function createCompiledQueryExpectation(mixed $users): string
{
    $ids = $users->pluck('id')->map(fn ($id) => "'{$id}'")->join(',');

    return implode('', [
    // NAME
    <<<QUERY
    UPDATE "users" SET `name` = (CASE\n
    QUERY,
    createWhenThenRegex($users, 'name'),

    // EMAIL
    <<<QUERY
    ELSE `name` END)
    ,`email` = (CASE\n
    QUERY,
    createWhenThenRegex($users, 'email'),

    // PASSWORD
    <<<QUERY
    ELSE `email` END)
    ,`password` = (CASE\n
    QUERY,
    createWhenThenRegex($users, 'password'),

    // UPDATED_AT
    <<<QUERY
    ELSE `password` END)
    ,`updated_at` = (CASE\n
    QUERY,
    createWhenThenRegex($users, 'updated_at'),

    // CREATED_AT
    <<<QUERY
    ELSE `updated_at` END)
    ,`created_at` = (CASE\n
    QUERY,
    createWhenThenRegex($users, 'created_at'),
    <<<QUERY
    ELSE `created_at` END) WHERE "id" IN({$ids});
    QUERY,
    ]);
}

function createWhenThenRegex(EloquentCollection $models, string $attribute): string
{
    return $models
        ->map(fn (Model $model) => with(
            $model->toArray(),
            fn ($asArray) => "WHEN `id` = '{$asArray[$model->getKeyName()]}' THEN '{$asArray[$attribute]}'"
        ))
        ->join(PHP_EOL).PHP_EOL;
}
