<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_media', function (Blueprint $table): void {
            $table->string('poster_path', 512)->nullable()->after('path');
            $table->string('processing_stage', 40)->default('queued')->after('processing_status');
            $table->unsignedTinyInteger('processing_progress')->default(0)->after('processing_stage');
            $table->unsignedInteger('processing_attempts')->default(0)->after('processing_progress');
            $table->timestamp('processing_started_at')->nullable()->after('processing_attempts');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_media', function (Blueprint $table): void {
            $table->dropColumn([
                'poster_path',
                'processing_stage',
                'processing_progress',
                'processing_attempts',
                'processing_started_at',
                'processing_completed_at',
            ]);
        });
    }
};
