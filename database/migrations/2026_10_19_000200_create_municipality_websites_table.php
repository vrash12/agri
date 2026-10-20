<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipality_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->unique()->constrained()->restrictOnDelete();
            $table->string('slug', 63)->unique();
            $table->boolean('is_published')->default(false)->index();
            $table->string('headline', 120);
            $table->string('tagline', 200)->nullable();
            $table->text('welcome_text')->nullable();
            $table->string('office_hours', 200)->nullable();
            $table->json('services')->nullable();
            $table->json('announcements')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_websites');
    }
};
