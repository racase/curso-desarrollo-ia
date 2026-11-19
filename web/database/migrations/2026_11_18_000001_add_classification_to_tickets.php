<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->string('priority')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('tickets')->whereNull('priority')->exists()) {
            throw new RuntimeException('Clasifica las incidencias sin prioridad antes de revertir. La reversión elimina las categorías; conserva una copia de seguridad.');
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->string('priority')->nullable(false)->default('medium')->change();
        });
    }
};
