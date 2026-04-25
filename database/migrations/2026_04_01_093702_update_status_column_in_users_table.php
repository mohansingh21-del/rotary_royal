<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Converts status from enum('Active','Inactive') to tinyint(1): 1=Active, 0=Inactive
     */
    public function up(): void
    {
        // Step 1: Add a temporary integer column
        DB::statement("ALTER TABLE users ADD COLUMN status_new TINYINT(1) NOT NULL DEFAULT 1");

        // Step 2: Copy data, converting enum values to integers
        DB::statement("UPDATE users SET status_new = CASE WHEN status = 'Active' THEN 1 ELSE 0 END");

        // Step 3: Drop the old enum column
        DB::statement("ALTER TABLE users DROP COLUMN status");

        // Step 4: Rename the new column to 'status'
        DB::statement("ALTER TABLE users RENAME COLUMN status_new TO status");
    }

    /**
     * Reverse the migrations.
     * Converts status back from tinyint(1) to enum('Active','Inactive')
     */
    public function down(): void
    {
        // Step 1: Add a temporary enum column
        DB::statement("ALTER TABLE users ADD COLUMN status_old ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'");

        // Step 2: Copy data back, converting integers to enum values
        DB::statement("UPDATE users SET status_old = CASE WHEN status = 1 THEN 'Active' ELSE 'Inactive' END");

        // Step 3: Drop the tinyint column
        DB::statement("ALTER TABLE users DROP COLUMN status");

        // Step 4: Rename back to 'status'
        DB::statement("ALTER TABLE users RENAME COLUMN status_old TO status");
    }
};
