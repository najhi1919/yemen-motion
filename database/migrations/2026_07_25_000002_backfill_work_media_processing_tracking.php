<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('work_media')
            ->whereNull('deleted_at')
            ->where('processing_status', 'ready')
            ->update([
                'processing_stage' => 'ready',
                'processing_progress' => 100,
                'processing_completed_at' => DB::raw('COALESCE(processing_completed_at, updated_at)'),
                'processing_error' => null,
            ]);

        DB::table('work_media')
            ->whereNull('deleted_at')
            ->where('processing_status', 'failed')
            ->update([
                'processing_stage' => 'failed',
            ]);
    }

    public function down(): void
    {
        // Data normalization is intentionally non-destructive and cannot be reversed safely.
    }
};
