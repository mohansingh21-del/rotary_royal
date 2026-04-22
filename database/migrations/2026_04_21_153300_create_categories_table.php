<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('event');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        // Seed initial categories
        $categories = ['General', 'Cultural', 'Sports', 'Educational', 'Social', 'Other'];
        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category,
                'type' => 'event',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
};
