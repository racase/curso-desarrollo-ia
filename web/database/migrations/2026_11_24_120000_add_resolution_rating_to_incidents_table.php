<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Avoid rebuilding incidents: it can cascade dependent comments/history
            // and rewrites older inline foreign keys that need native rollback.
            DB::statement('ALTER TABLE incidents ADD COLUMN closing_technician_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT');
        } else {
            Schema::table('incidents', fn (Blueprint $table) => $table->foreignId('closing_technician_id')->nullable()->constrained('users')->restrictOnDelete());
        }
        Schema::table('incidents', function (Blueprint $table) {
            $table->index('closing_technician_id');
            $table->timestamp('rated_at')->nullable();
        });

        // Column-level CHECK works on both PostgreSQL and SQLite without rebuilding historical rows.
        DB::statement("ALTER TABLE incidents ADD COLUMN resolution_rating SMALLINT CONSTRAINT incidents_resolution_rating_valid CHECK (
            (resolution_rating IS NULL AND rated_at IS NULL) OR
            (resolution_rating IS NOT NULL AND resolution_rating IN (1, 2, 3, 4, 5)
                AND rated_at IS NOT NULL AND status = 'closed' AND closing_technician_id IS NOT NULL)
        )");
        DB::table('incidents')->where('status', 'closed')->whereNotNull('technician_id')
            ->update(['closing_technician_id' => DB::raw('technician_id')]);
    }

    public function down(): void
    {
        Schema::table('incidents', fn (Blueprint $table) => $table->dropColumn('resolution_rating'));
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('incidents', fn (Blueprint $table) => $table->dropIndex(['closing_technician_id']));
            DB::statement('ALTER TABLE incidents DROP COLUMN closing_technician_id');
            DB::statement('ALTER TABLE incidents DROP COLUMN rated_at');

            return;
        }
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['closing_technician_id']);
            $table->dropConstrainedForeignId('closing_technician_id');
            $table->dropColumn('rated_at');
        });
    }
};
