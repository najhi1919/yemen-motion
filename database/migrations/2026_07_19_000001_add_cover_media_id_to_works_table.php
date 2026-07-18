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
        Schema::table('works', function (Blueprint $table) {
            $table->foreignId('cover_media_id')
                ->nullable()
                ->constrained('work_media')
                ->nullOnDelete();
            $table->index('cover_media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('works', function (Blueprint $table) {
            $table->dropForeign(['cover_media_id']);
            $table->dropColumn('cover_media_id');
        });
    }
};
