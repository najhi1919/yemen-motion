<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table): void {
            $table->string('avatar_path', 512)->nullable()->after('bio');
            $table->string('cover_path', 512)->nullable()->after('avatar_path');
            $table->unsignedTinyInteger('cover_focal_x')->default(50)->after('cover_path');
            $table->unsignedTinyInteger('cover_focal_y')->default(50)->after('cover_focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'avatar_path',
                'cover_path',
                'cover_focal_x',
                'cover_focal_y',
            ]);
        });
    }
};
