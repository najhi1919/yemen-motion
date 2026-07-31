<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designer_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->text('professional_note')->nullable();
            $table->boolean('show_availability_publicly')->default(true);
            $table->boolean('show_specialties_publicly')->default(true);
            $table->boolean('show_skills_publicly')->default(true);
            $table->boolean('show_tools_publicly')->default(true);
            $table->boolean('show_languages_publicly')->default(true);
            $table->boolean('show_experience_publicly')->default(true);
        });

        Schema::create('designer_profile_specialties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('designer_profile_id')->constrained('designer_profiles')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('name', 80);
            $table->string('normalized_name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['designer_profile_id', 'kind', 'sort_order']);
            $table->unique(['designer_profile_id', 'kind', 'normalized_name']);
        });

        foreach (['skills', 'tools'] as $type) {
            Schema::create("designer_profile_{$type}", function (Blueprint $table): void {
                $table->id();
                $table->foreignId('designer_profile_id')->constrained('designer_profiles')->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('normalized_name', 80);
                $table->string('level', 24);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['designer_profile_id', 'sort_order']);
                $table->unique(['designer_profile_id', 'normalized_name']);
            });
        }

        Schema::create('designer_profile_languages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('designer_profile_id')->constrained('designer_profiles')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('normalized_name', 80);
            $table->string('level', 24);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['designer_profile_id', 'sort_order']);
            $table->unique(['designer_profile_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_profile_languages');
        Schema::dropIfExists('designer_profile_tools');
        Schema::dropIfExists('designer_profile_skills');
        Schema::dropIfExists('designer_profile_specialties');

        Schema::table('designer_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'years_of_experience',
                'professional_note',
                'show_availability_publicly',
                'show_specialties_publicly',
                'show_skills_publicly',
                'show_tools_publicly',
                'show_languages_publicly',
                'show_experience_publicly',
            ]);
        });
    }
};
