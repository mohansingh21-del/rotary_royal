<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_dummy')->default(false)->after('status')->index();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_dummy')->default(false)->after('status')->index();
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->boolean('is_dummy')->default(false)->after('is_marquee')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropIndex(['is_dummy']);
            $table->dropColumn('is_dummy');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['is_dummy']);
            $table->dropColumn('is_dummy');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_dummy']);
            $table->dropColumn('is_dummy');
        });
    }
};
