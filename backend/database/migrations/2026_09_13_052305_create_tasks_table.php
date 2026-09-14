<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('deadline')->nullable();
            $table->string('priority', 20);
            $table->string('status', 20);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['workspace_id', 'deleted_at']);
            $table->index(['workspace_id', 'project_id']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'priority']);
            $table->index(['workspace_id', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
