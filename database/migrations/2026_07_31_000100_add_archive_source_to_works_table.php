<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->string('archived_from_status', 40)->nullable();
            $table->string('archived_from_visibility_status', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->dropColumn([
                'archived_from_status',
                'archived_from_visibility_status',
            ]);
        });
    }
};
