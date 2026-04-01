<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->string('id')->primary(); // Example: BR-1001
            $table->foreignId('user_id')->constrained();
            $table->foreignId('asset_id')->constrained();
            $table->string('contact_number');
            $table->string('id_type');
            $table->string('id_number');
            $table->string('id_image_path')->nullable();
            $table->string('time_slot');
            $table->date('booking_date');
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Completed'])->default('Pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};