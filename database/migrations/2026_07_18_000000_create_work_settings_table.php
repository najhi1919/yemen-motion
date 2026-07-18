<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 50)->unique();
            $table->json('values');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('work_settings')->insert([
            'scope' => 'global',
            'values' => '{}',
            'version' => 1,
            'updated_by' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('work_settings');
    }
};
