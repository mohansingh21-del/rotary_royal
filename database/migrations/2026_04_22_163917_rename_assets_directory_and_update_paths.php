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
        $oldPath = public_path('assets');
        $newPath = public_path('inventory_assets');

        if (file_exists($oldPath) && !file_exists($newPath)) {
            rename($oldPath, $newPath);
        }

        DB::table('assets')->where('image', 'like', '/assets/%')
            ->update([
                'image' => DB::raw("REPLACE(image, '/assets/', '/inventory_assets/')")
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $oldPath = public_path('inventory_assets');
        $newPath = public_path('assets');

        if (file_exists($oldPath) && !file_exists($newPath)) {
            rename($oldPath, $newPath);
        }

        DB::table('assets')->where('image', 'like', '/inventory_assets/%')
            ->update([
                'image' => DB::raw("REPLACE(image, '/inventory_assets/', '/assets/')")
            ]);
    }
};
