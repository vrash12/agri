<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipality_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->unique()->constrained()->onDelete('restrict');
            $table->string('office_name', 100);
            $table->string('website_url', 200)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('office_address', 200)->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signatory_name', 100)->nullable();
            $table->string('signatory_title', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_profiles');
    }
};
