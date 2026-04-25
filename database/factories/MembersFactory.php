<?php

namespace Database\Factories;

use App\Models\Members;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembersFactory extends Factory
{
    protected $model = Members::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'member_id' => $this->faker->unique()->numerify('MEM-####'),
            'address' => $this->faker->address(),
            'dob' => $this->faker->date(),
            'gender' => $this->faker->randomElement(['Male', 'Female', 'Other']),
            'image' => 'members/default.jpg',
            'work' => $this->faker->jobTitle(),
            'date_of_joining' => $this->faker->date(),
        ];
    }
}
