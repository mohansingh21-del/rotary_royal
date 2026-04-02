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
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('reference');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->index('category');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            $table->dropIndex(['reference']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['status']);
        });
    }
};
