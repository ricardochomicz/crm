<?php

namespace Database\Factories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Operator> */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        return ['name' => fake()->company(), 'slug' => fake()->unique()->slug(2), 'is_active' => true];
    }
}
