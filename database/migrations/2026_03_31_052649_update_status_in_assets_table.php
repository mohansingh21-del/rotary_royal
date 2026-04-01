<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('status_new')->default(1)->after('status');
        });

        DB::table('assets')->where('status', 'Available')->update(['status_new' => 1]);
        DB::table('assets')->where('status', 'Not Available')->update(['status_new' => 0]);

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('assets', function (Blueprint $table) {
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
        Schema::table('assets', function (Blueprint $table) {
            $table->enum('status_old', ['Available', 'Not Available'])->default('Available')->after('status');
        });

        DB::table('assets')->where('status', 1)->update(['status_old' => 'Available']);
        DB::table('assets')->where('status', 0)->update(['status_old' => 'Not Available']);

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('status_old', 'status');
        });
    }
};