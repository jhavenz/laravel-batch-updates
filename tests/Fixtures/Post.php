<?php

namespace Jhavenz\LaravelBatchUpdate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\WithFaker;

class Post extends Model
{
    use HasUuids;
    use HasFactory;
    use WithFaker;

    protected static function newFactory(): Factory
    {
        return new class () extends Factory {
            protected $model = Post::class;

            public function definition(): array
            {
                return [
                    'title' => $this->faker->sentence(),
                    'slug' => fn ($attrs) => $attrs['title'],
                    'body' => $this->faker->text(),
                    'user_id' => User::factory(),
                ];
            }
        };
    }
}
