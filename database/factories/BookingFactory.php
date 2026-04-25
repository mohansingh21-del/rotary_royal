<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition()
    {
        $startDate = Carbon::now()->addDays(rand(1, 10))->setHour(10)->setMinute(0);
        $endDate = $startDate->copy()->addHours(2);
        
        return [
            'id' => 'BR-' . $this->faker->unique()->numberBetween(1000, 9000),
            'user_id' => User::factory(),
            'asset_id' => Asset::factory(),
            'user_name' => $this->faker->name(),
            'user_phone' => '9' . $this->faker->numerify('#########'),
            'user_email' => $this->faker->safeEmail(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reference' => null,
            'id_number' => $this->faker->numerify('ID#######'),
            'id_image_path' => 'payments/default_id.jpg',
            'payment_image' => 'payments/default_payment.jpg',
            'status' => 'Approved',
            'buffer_time' => 2,
            'rejection_reason' => null,
        ];
    }
}
