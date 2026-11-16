<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description');
            $table->string('status')->default('proposed');
            $table->string('priority')->default('medium');
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('tracking_version')->default(0);
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('priority');
            $table->index('requester_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
