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
        Schema::create('watchlist_terms', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();
            $table->string('term');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_terms');
    }
};
