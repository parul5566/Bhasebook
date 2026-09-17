<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AiRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mode' => 'caption',
            'succeeded' => true,
        ];
    }
}
