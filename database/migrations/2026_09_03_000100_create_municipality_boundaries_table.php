<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipality_boundaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('name', 150);
            $table->json('geojson');
            $table->string('color', 7)->default('#15803D');
            $table->string('status', 20)->default('draft');
            $table->decimal('area_ha', 15, 4);
            $table->decimal('centroid_lat', 10, 7);
            $table->decimal('centroid_lng', 11, 7);
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 11, 7);
            $table->decimal('max_lng', 11, 7);
            $table->unsignedInteger('vertex_count');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(
                ['municipality_id', 'status'],
                'municipality_boundary_scope_index'
            );
            $table->index(
                ['min_lat', 'max_lat', 'min_lng', 'max_lng'],
                'municipality_boundary_bbox_index'
            );

            $table->foreign('municipality_id', 'municipality_boundary_municipality_fk')
                ->references('id')
                ->on('municipalities')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('created_by', 'municipality_boundary_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'municipality_boundary_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_boundaries');
    }
};
