<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            // Remove old column
            $table->dropColumn('category');

            // Add new column
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null')->after('longitude');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
            $table->enum('category', ['General', 'Cultural', 'Sports', 'Educational', 'Social', 'Other'])->default('General')->after('longitude');
        });
    }
};
