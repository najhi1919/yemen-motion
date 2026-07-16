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
        Schema::create('work_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained('works')->restrictOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code', 50);
            $table->text('details')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index('work_id');
            $table->index(['work_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['reporter_id', 'created_at']);
            $table->index('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_reports');
    }
};
