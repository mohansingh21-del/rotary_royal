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
        Schema::table('bookings', function (Blueprint $table) {
            // Make user_id nullable for guest bookings
            $table->foreignId('user_id')->nullable()->change();
            
            // Guest Information (from form screenshot)
            $table->string('user_name')->after('user_id'); // Full Name
            $table->string('user_phone')->after('user_name'); // Mobile Number
            $table->string('user_email')->after('user_phone'); // Email
            
            // Date & Time
            $table->dateTime('start_date')->after('user_email');
            $table->dateTime('end_date')->after('start_date');
            
            // ID Proof Already exists (id_number, id_image_path)
            
            // Misc
            $table->string('reference')->nullable()->after('end_date'); // Reference (Optional)
            
            // Drop old fields
            $table->dropColumn(['contact_number', 'time_slot', 'booking_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->string('contact_number')->after('asset_id');
            $table->string('time_slot')->after('id_image_path');
            $table->date('booking_date')->after('time_slot');
            $table->dropColumn(['user_name', 'user_phone', 'user_email', 'start_date', 'end_date', 'reference']);
        });
    }
};
