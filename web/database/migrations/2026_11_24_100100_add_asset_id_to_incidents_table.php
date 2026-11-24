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
            // Native ADD COLUMN preserves dependent rows; rebuilding incidents can cascade deletes.
            DB::statement('ALTER TABLE incidents ADD COLUMN asset_id INTEGER NULL REFERENCES assets(id) ON DELETE RESTRICT');
            Schema::table('incidents', fn (Blueprint $table) => $table->index('asset_id'));

            return;
        }
        Schema::table('incidents', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->index()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('incidents', fn (Blueprint $table) => $table->dropIndex(['asset_id']));
            DB::statement('ALTER TABLE incidents DROP COLUMN asset_id');

            return;
        }
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['asset_id']);
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
