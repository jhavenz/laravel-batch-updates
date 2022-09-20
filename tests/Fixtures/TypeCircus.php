<?php

namespace Jhavenz\LaravelBatchUpdate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;

class TypeCircus extends Model
{
    use HasFactory;
    use WithFaker;

    protected $casts = [
        'date_column' => 'immutable_date',
        'datetime_column' => 'immutable_datetime',
        'time_column' => 'immutable_datetime:H:i:s',
        'timestamp_column' => 'immutable_datetime:U',
        'boolean_column' => 'boolean',
        'double_column' => 'double',
        'decimal_column' => 'decimal:2',
        'float_column' => 'float',
        'json_column' => 'array',
        'object_column' => 'object',
    ];

    protected static function newFactory(): Factory
    {
        return new class () extends Factory {
            protected $model = TypeCircus::class;

            public function definition(): array
            {
                return [
                    'date_column' => $this->faker->dateTimeThisMonth(),
                    'datetime_column' => $this->faker->dateTimeThisMonth(),
                    'time_column' => $this->faker->time(),
                    'timestamp_column' => $this->faker->dateTimeThisMonth()->getTimestamp(),
                    'boolean_column' => $this->faker->boolean(),
                    'double_column' => $this->faker->latitude(),
                    'decimal_column' => rand(01.01, 99.99),
                    'float_column' => $this->faker->randomFloat(),
                    'json_column' => $this->createWordMap(),
                    'object_column' => $this->createWordMap(),
                ];
            }

            /**
             * @return array
             */
            private function createWordMap(): array
            {
                return Collection::times(rand(2, 10), fn () => [
                    $this->faker->word() => $this->faker->sentence(),
                ])->all();
            }
        };
    }
}
