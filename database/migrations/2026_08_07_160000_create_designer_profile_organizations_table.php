<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_profile_organizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('designer_profile_id')
                ->unique()
                ->constrained('designer_profiles')
                ->cascadeOnDelete();

            $table->string('organization_name', 160);
            $table->string('organization_type', 32);
            $table->text('description')->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->boolean('show_publicly')->default(true);

            $table->timestampsTz(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_profile_organizations');
    }
};
