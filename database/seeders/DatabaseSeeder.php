<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // 1. Create Super Admin
        User::updateOrCreate(
        ['email' => 'admin@rotary.com'],
        [
            'name' => 'Super Admin',
            'password' => Hash::make('admin123'),
            'phone' => '1234567890',
            'role' => 'Super Admin',
            'status' => 'Active',
        ]
        );

        // 2. Create Sample Assets
        Asset::create([
            'name' => 'Oxygen Cylinder (10L)',
            'category' => 'Asset',
            'quantity' => 15,
            'buffer_time' => 2,
            'price' => 500.00,
            'status' => 'Available',
        ]);

        // Asset::create([
        //     'name' => 'Medical Hospital Bed',
        //     'category' => 'Asset',
        //     'quantity' => 10,
        //     'buffer_time' => 3,
        //     'price' => 1200.00,
        //     'status' => 'Available',
        // ]);

        // Asset::create([
        //     'name' => 'N95 Mask Pack',
        //     'category' => 'Consumable',
        //     'quantity' => 100,
        //     'buffer_time' => 0,
        //     'price' => 50.00,
        //     'status' => 'Available',
        // ]);

        // 3. Create Sample Donations
        Donation::create([
            'donor_name' => 'John Doe',
            'amount' => 5000.00,
            'date' => now()->subDays(5),
            'foundation_name' => 'Rotary Foundation',
            'is_marquee' => true,
        ]);

        // Donation::create([
        //     'donor_name' => 'Jane Smith',
        //     'amount' => 2500.00,
        //     'date' => now()->subDays(2),
        //     'foundation_name' => 'Local Trust',
        //     'is_marquee' => true,
        // ]);

        // 4. Create Sample Booking (New Schema)
        \App\Models\Booking::create([
            'id' => 'BR-1001',
            'asset_id' => 1,
            'user_name' => 'Rahul Sharma',
            'user_phone' => '9876543210',
            'user_email' => 'rahul@example.com',
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(5),
            'id_type' => 'Aadhar Card',
            'id_number' => '1234-5678-9012',
            'id_image_path' => 'aadhar_front.jpg',
            'status' => 'Approved',
        ]);
    }
}