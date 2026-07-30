<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->string('cover_display_mode', 8)->default('fill');
            $table->unsignedTinyInteger('cover_focal_x')->default(50);
            $table->unsignedTinyInteger('cover_focal_y')->default(50);
        });
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->dropColumn([
                'cover_display_mode',
                'cover_focal_x',
                'cover_focal_y',
            ]);
        });
    }
};
