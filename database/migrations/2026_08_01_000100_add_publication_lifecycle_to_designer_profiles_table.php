<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table): void {
            $table->timestamp('hidden_at')->nullable();
            $table->index('publication_status');
        });
    }

    public function down(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table): void {
            $table->dropIndex(['publication_status']);
            $table->dropColumn('hidden_at');
        });
    }
};
