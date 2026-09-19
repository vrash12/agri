<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_portal_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->unique()->constrained('farmers')->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->string('login_id', 40)->unique();
            $table->string('password')->nullable();
            $table->char('activation_code_hash', 64)->nullable();
            $table->timestamp('activation_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('session_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->index(['municipality_id', 'is_active'], 'farmer_portal_scope_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_portal_accounts');
    }
};
