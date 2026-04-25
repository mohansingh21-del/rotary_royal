<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word() . ' Asset',
            'category' => $this->faker->randomElement(['Asset', 'Consumable']),
            'quantity' => 10,
            'left_quantity' => 10,
            'buffer_time' => 2,
            'price' => 100.00,
            'image' => 'assets/default.jpg',
            'status' => 1,
        ];
    }
}
