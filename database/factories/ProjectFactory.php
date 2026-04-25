<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition()
    {
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'banner_image' => 'projects/default.jpg',
            'start_date' => clone $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => clone $this->faker->dateTimeBetween('+2 months', '+6 months'),
            'is_funding_available' => true,
            'is_active' => 1,
        ];
    }
}
