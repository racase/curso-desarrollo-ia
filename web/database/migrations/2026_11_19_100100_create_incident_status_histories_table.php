<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->timestamps();
            $table->index(['incident_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_status_histories');
    }
};
