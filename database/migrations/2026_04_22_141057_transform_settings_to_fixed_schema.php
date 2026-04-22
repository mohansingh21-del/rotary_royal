<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Admin Bank Details
            $table->string('account_holder_name')->nullable()->after('id');
            $table->string('account_number')->nullable()->after('account_holder_name');
            $table->string('ifsc_code')->nullable()->after('account_number');
            $table->string('bank_name')->nullable()->after('ifsc_code');
            $table->string('branch')->nullable()->after('bank_name');
            
            // Club Details
            $table->string('contact_numbers')->nullable()->after('branch');
            $table->string('email_address')->nullable()->after('contact_numbers');
            $table->text('office_address')->nullable()->after('email_address');
            
            // UPI & QR Code
            $table->string('upi_id')->nullable()->after('office_address');
            $table->string('payment_qr_code')->nullable()->after('upi_id');

            // Marquee Settings
            $table->string('marquee_common_message')->nullable()->after('payment_qr_code');
            $table->json('marquee_items')->nullable()->after('marquee_common_message');
        });

        // Migrate existing donation_settings data
        $donationSetting = DB::table('settings')->where('key', 'donation_settings')->first();
        if ($donationSetting) {
            $val = json_decode($donationSetting->value, true);
            DB::table('settings')->updateOrInsert(
                ['id' => 1],
                [
                    'account_holder_name' => $val['account_holder_name'] ?? null,
                    'account_number'      => $val['account_number'] ?? null,
                    'ifsc_code'           => $val['ifsc_code'] ?? null,
                    'bank_name'           => $val['bank_name'] ?? null,
                    'branch'              => $val['branch'] ?? null,
                    'contact_numbers'     => $val['contact_numbers'] ?? null,
                    'email_address'       => $val['email_address'] ?? null,
                    'office_address'      => $val['office_address'] ?? null,
                    'upi_id'              => $val['upi_id'] ?? null,
                    'payment_qr_code'     => $val['payment_qr_code'] ?? null,
                    'updated_at'          => now(),
                ]
            );
        }

        // Migrate marquee_settings data
        $marqueeSetting = DB::table('settings')->where('key', 'marquee_settings')->first();
        if ($marqueeSetting) {
            $val = json_decode($marqueeSetting->value, true);
            DB::table('settings')->where('id', 1)->update([
                'marquee_common_message' => $val['commonMessage'] ?? null,
                'marquee_items'          => isset($val['items']) ? json_encode($val['items']) : null,
                'updated_at'             => now(),
            ]);
        }

        // Drop the old key-value columns
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['key', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('key')->unique()->nullable()->after('id');
            $table->json('value')->nullable()->after('key');
        });

        // Restore data from columns to JSON if needed (simplified)
        
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'account_holder_name', 'account_number', 'ifsc_code', 'bank_name', 'branch',
                'contact_numbers', 'email_address', 'office_address', 'upi_id', 'payment_qr_code',
                'marquee_common_message', 'marquee_items'
            ]);
        });
    }
};
