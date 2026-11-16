<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $users = DB::table('users')->orderBy('id')->get(['id', 'email']);
            $normalized = $users->map(fn ($user) => mb_strtolower(trim($user->email)));
            if ($normalized->duplicates()->isNotEmpty()) {
                throw new RuntimeException('Existen correos duplicados al normalizarlos. Resuelve las cuentas afectadas antes de migrar.');
            }
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['email' => mb_strtolower(trim($user->email))]);
            }
            Schema::create('administration_locks', function (Blueprint $table): void {
                $table->string('name')->primary();
            });
            DB::table('administration_locks')->insert(['name' => 'users']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_locks');
    }
};
