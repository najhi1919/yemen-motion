<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_profile_featured_works', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('designer_profile_id')
                ->constrained('designer_profiles')
                ->cascadeOnDelete();
            $table->foreignId('work_id')
                ->constrained('works')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(
                ['designer_profile_id', 'work_id'],
                'designer_profile_featured_work_unique',
            );
            $table->unique(
                ['designer_profile_id', 'position'],
                'designer_profile_featured_position_unique',
            );
            $table->index(
                ['work_id', 'designer_profile_id'],
                'designer_profile_featured_work_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_profile_featured_works');
    }
};
