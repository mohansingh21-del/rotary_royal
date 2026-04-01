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
        Schema::table('bookings', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->string('payment_image')->nullable()->after('id_image_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('payment_image');
        });
    }
};
