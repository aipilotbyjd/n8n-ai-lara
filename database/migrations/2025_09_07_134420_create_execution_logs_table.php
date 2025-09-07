<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('executions')->onDelete('cascade');
            $table->string('node_id')->nullable();
            $table->enum('level', ['debug', 'info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();

            $table->index(['execution_id', 'level']);
            $table->index(['execution_id', 'node_id']);
            $table->index(['execution_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
