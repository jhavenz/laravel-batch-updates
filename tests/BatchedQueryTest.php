<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    $data = Collection::times($users->count(), function ($i) use ($users) {
        return [
            'id' => $users[$i - 1]->id,
            'name' => fake()->name(),
            'email' => fake()->email(),
        ];
    });

    $userIds = $users->pluck('id')->join(',');

    $batchUpdate = BatchedUpdate::createFromModel($users)->compileUpdateQuery($data);

    $expectedQueryResults = implode('\n', [
    <<<QUERY
    UPDATE "users" SET `name` = (CASE \n
    QUERY,
    createWhenThens(5),
    <<<QUERY
    ELSE `name` END) \n
    ,`users` SET `email` = (CASE \n
    QUERY,
    createWhenThens(5),
    <<<QUERY
    ELSE `email` END) \n
    ,`users` SET `updated_at` = (CASE \n
    QUERY,
    createWhenThens(5),
    <<<QUERY
    ELSE `updated_at` END) WHERE "id" IN($userIds);
    QUERY,
    ]);

    $comparison = str($batchUpdate->getCompiledQuery())->is($expectedQueryResults);

    expect($comparison)->toBeTrue();
});


function createWhenThens(int $count): string
{
    return Collection::times($count, function () {
        return <<<MESSAGE
        WHEN `id` = '*' THEN '*'\n
        MESSAGE;
    })->join('\n');
}
