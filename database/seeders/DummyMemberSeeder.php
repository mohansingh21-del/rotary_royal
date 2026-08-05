<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Donation;
use App\Models\DonationReceipt;
use App\Models\Members;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the account the Play Store / App Store reviewers log in with.
 *
 * Everything created here carries `is_dummy = true`, which the visibility
 * scope uses to keep it away from real users, admins and public feeds. The
 * bookings point at the club's real assets — the review account browses the
 * genuine catalog — but they never consume stock, so no real inventory,
 * total or report shifts because of them.
 *
 * More than one row of each on purpose: a reviewer who lands on an empty list
 * screen reads it as a broken app.
 *
 * Idempotent and authoritative: re-running resets the account to exactly this
 * data, pruning any review record no longer defined here — including bookings
 * a reviewer made on a previous pass. Real records are never touched.
 *
 *     php artisan db:seed --class=DummyMemberSeeder
 */
class DummyMemberSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $user = $this->seedUser();
            $this->seedBookings($user);
            $this->seedDonations($user);
            $this->seedNotifications($user);
        });

        $this->command?->info('Review account seeded: ' . config('dummy.phone') . ' / OTP ' . config('dummy.otp'));

        if (!config('dummy.enabled')) {
            $this->command?->warn('DUMMY_LOGIN_ENABLED is false — the account exists but cannot log in. Set it to true for the review window.');
        }
    }

    private function seedUser(): User
    {
        $user = User::updateOrCreate(
            ['email' => config('dummy.email')],
            [
                'name' => 'Android Test User',
                'phone' => config('dummy.phone'),
                // Password login is blocked for this account; it is OTP only.
                'password' => Hash::make(Str::random(40)),
                'role' => 'Member',
                'status' => 1,
                'is_dummy' => true,
            ]
        );

        Members::updateOrCreate(
            ['user_id' => $user->id],
            [
                'member_id' => 'RR-TEST-001',
                'address' => '12 MG Road, Indore, Madhya Pradesh',
                'dob' => '1988-06-14',
                'gender' => 'Other',
                'work' => 'Business',
                'date_of_joining' => now()->subYears(3)->toDateString(),
            ]
        );

        return $user;
    }

    /**
     * One booking per status so the reviewer's tabs and filters all have data.
     *
     * Uses whatever real assets exist — these bookings are flagged, so they
     * hold no stock and are invisible to everyone but the review account.
     */
    private function seedBookings(User $user): void
    {
        $assets = Asset::orderBy('id')->take(3)->get();

        if ($assets->isEmpty()) {
            $this->command?->warn('No assets in the database — skipping review bookings. Seed assets first, then re-run.');
            return;
        }

        $plans = [
            ['BR-D-1001', 'Approved', 3, 5, null],
            ['BR-D-1002', 'Completed', -20, -14, null],
            ['BR-D-1003', 'Rejected', -6, -4, 'Requested dates were unavailable.'],
        ];

        foreach ($plans as $index => [$id, $status, $startOffset, $endOffset, $rejectionReason]) {
            // Cycle through whatever assets are available.
            $asset = $assets[$index % $assets->count()];

            Booking::withDummy()->updateOrCreate(
                ['id' => $id],
                [
                    'user_id' => $user->id,
                    'asset_id' => $asset->id,
                    'user_name' => $user->name,
                    'user_phone' => $user->phone,
                    'user_email' => $user->email,
                    'start_date' => now()->addDays($startOffset)->startOfDay(),
                    'end_date' => now()->addDays($endOffset)->startOfDay(),
                    'reference' => null,
                    'id_number' => 'TEST-ID-' . Str::afterLast($id, '-'),
                    'id_image_path' => null,
                    'payment_image' => null,
                    'status' => $status,
                    'buffer_time' => $asset->buffer_time,
                    'rejection_reason' => $rejectionReason,
                    'is_dummy' => true,
                ]
            );
        }

        Booking::withDummy()
            ->where('is_dummy', true)
            ->whereNotIn('id', array_column($plans, 0))
            ->delete();
    }

    private function seedDonations(User $user): void
    {
        $rows = [
            ['TEST-RCPT-001', 2100, 'Rotary Foundation', 12],
            ['TEST-RCPT-002', 5100, 'Rotary Foundation', 45],
            ['TEST-RCPT-003', 1100, 'Rotary Club', 90],
        ];

        $keptIds = [];

        foreach ($rows as [$receiptNo, $amount, $foundation, $daysAgo]) {
            $donation = Donation::withDummy()->updateOrCreate(
                ['mobile_no' => $user->phone, 'is_dummy' => true, 'amount' => $amount],
                [
                    'project_id' => null,
                    'donor_name' => $user->name,
                    'date' => now()->subDays($daysAgo)->toDateString(),
                    'time' => now()->subDays($daysAgo)->toTimeString(),
                    'foundation_name' => $foundation,
                    // Never on the public marquee.
                    'is_marquee' => false,
                    'payment_receipt' => null,
                    'is_dummy' => true,
                ]
            );

            DonationReceipt::updateOrCreate(
                ['donation_id' => $donation->id],
                [
                    'user_id' => $user->id,
                    'receipt_no' => $receiptNo,
                    'transaction_id' => '#RR-' . Str::afterLast($receiptNo, '-'),
                    'status' => 'Completed',
                    'pdf_path' => null,
                ]
            );

            $keptIds[] = $donation->id;
        }

        $staleIds = Donation::withDummy()
            ->where('is_dummy', true)
            ->whereNotIn('id', $keptIds)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            DonationReceipt::whereIn('donation_id', $staleIds)->delete();
            Donation::withDummy()->whereIn('id', $staleIds)->delete();
        }
    }

    /**
     * Written straight to the table rather than through notify(), which would
     * also try the mail channel and bounce off the placeholder address.
     */
    private function seedNotifications(User $user): void
    {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->delete();

        $booking = Booking::withDummy()->where('id', 'BR-D-1001')->with('asset')->first();
        $assetName = $booking?->asset?->name ?? 'your booking';

        $rows = [
            ['App\\Notifications\\BookingConfirmedNotification', [
                'booking_id' => 'BR-D-1001',
                'asset_name' => $assetName,
                'message' => 'Your booking for ' . $assetName . ' has been confirmed.',
                'type' => 'booking_confirmed',
            ], 2],
            ['App\\Notifications\\DonationReceiptReadyNotification', [
                'message' => 'Your donation receipt TEST-RCPT-001 is ready to download.',
                'type' => 'donation_receipt_ready',
            ], 12],
            ['App\\Notifications\\BookingRejectedNotification', [
                'booking_id' => 'BR-D-1003',
                'asset_name' => $assetName,
                'message' => 'Your booking request was not approved.',
                'type' => 'booking_rejected',
            ], 6],
        ];

        foreach ($rows as [$type, $data, $daysAgo]) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode($data),
                'read_at' => null,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }
    }
}
