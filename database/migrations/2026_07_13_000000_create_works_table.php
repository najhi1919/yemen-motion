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
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->index();
            $table->string('visibility_status')->default('hidden')->index();
            $table->string('media_type')->nullable()->index();
            $table->decimal('price_amount', 15, 2)->nullable();
            $table->unsignedInteger('delivery_days')->nullable();

            $table->foreignId('designer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('category_id')->nullable()->index();

            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('is_trusted_direct_publish')->default(false)->index();

            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);

            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->text('change_request_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};
