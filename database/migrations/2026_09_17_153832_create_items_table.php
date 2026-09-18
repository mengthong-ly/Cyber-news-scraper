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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20)->index();
            $table->text('url');
            $table->char('url_hash', 40)->unique();
            $table->char('content_hash', 40)->index();
            $table->string('title', 500);
            $table->string('title_en', 500)->nullable();
            $table->text('excerpt')->nullable();
            $table->text('summary_en')->nullable();
            $table->string('publisher')->nullable();
            $table->string('author')->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('language', 10)->nullable();
            $table->string('category', 20)->default('general')->index();
            $table->unsignedTinyInteger('severity')->default(1)->index();
            $table->boolean('is_cambodia')->default(false)->index();
            $table->json('watch_hits')->nullable();
            $table->json('entities')->nullable();
            $table->boolean('ai_allowed')->default(true);
            $table->string('enrichment_status', 20)->default('skipped')->index();
            $table->timestamp('enriched_at')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
