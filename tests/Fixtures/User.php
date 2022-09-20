<?php

namespace Jhavenz\LaravelBatchUpdate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\WithFaker;

class User extends Model
{
    use HasFactory;
    use WithFaker;

    protected static function newFactory(): Factory
    {
        return new class () extends Factory {
            protected $model = User::class;

            public function definition(): array
            {
                return [
                    'name' => $this->faker->name(),
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                ];
            }
        };
    }
}
