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
        Schema::create('work_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('slug', 160)->unique();
            $table->timestamp('disabled_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['disabled_at', 'sort_order', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_tags');
    }
};
