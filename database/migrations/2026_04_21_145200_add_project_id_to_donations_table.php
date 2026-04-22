<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('set null')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
