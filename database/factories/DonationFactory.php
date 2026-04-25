<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition()
    {
        return [
            'project_id' => Project::factory(),
            'donor_name' => $this->faker->name(),
            'mobile_no' => '9' . $this->faker->numerify('#########'),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'date' => $this->faker->date(),
            'time' => $this->faker->time(),
            'foundation_name' => $this->faker->company(),
            'is_marquee' => $this->faker->boolean(20),
            'payment_receipt' => 'receipts/default.jpg',
        ];
    }
}
