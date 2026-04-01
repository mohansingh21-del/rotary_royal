<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

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
            $table->enum('status_new', ['Approved', 'Rejected', 'Completed'])->default('Approved')->after('status');
        });

        DB::table('bookings')->where('status', 'Approved')->update(['status_new' => 'Approved']);
        DB::table('bookings')->where('status', 'Rejected')->update(['status_new' => 'Rejected']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
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
            $table->enum('status_old', ['Approved', 'Rejected'])->default('Approved')->after('status');
        });

        DB::table('bookings')->whereIn('status', ['Approved', 'Completed'])->update(['status_old' => 'Approved']);
        DB::table('bookings')->where('status', 'Rejected')->update(['status_old' => 'Rejected']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('status_old', 'status');
        });
    }
};
