<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('left_quantity')->default(0)->after('quantity');
        });

        // Seed left_quantity based on current approved bookings
        $assets = DB::table('assets')->whereNull('deleted_at')->get();
        foreach ($assets as $asset) {
            $approved = DB::table('bookings')
                ->where('asset_id', $asset->id)
                ->where('status', 'Approved')
                ->count();
            $left = max(0, $asset->quantity - $approved);
            DB::table('assets')->where('id', $asset->id)->update(['left_quantity' => $left]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('left_quantity');
        });
    }
};
