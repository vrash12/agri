<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agricultural_machineries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->unsignedBigInteger('farmers_cooperative_id')->nullable();
            $table->string('asset_code', 60);
            $table->string('name', 150);
            $table->string('category', 50);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->unsignedSmallInteger('year_acquired')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->string('acquisition_source', 40)->nullable();
            $table->decimal('acquisition_cost', 14, 2)->nullable();
            $table->string('condition_status', 30)->default('good');
            $table->string('availability_status', 30)->default('available');
            $table->string('location')->nullable();
            $table->decimal('service_hours', 12, 2)->nullable();
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['municipality_id', 'asset_code'],
                'machinery_municipality_asset_unique'
            );
            $table->index(
                ['municipality_id', 'availability_status'],
                'machinery_municipality_availability_index'
            );
            $table->index(
                ['municipality_id', 'condition_status'],
                'machinery_municipality_condition_index'
            );
            $table->index(
                ['municipality_id', 'next_maintenance_date'],
                'machinery_municipality_maintenance_index'
            );

            $table->foreign('municipality_id', 'machinery_municipality_fk')
                ->references('id')
                ->on('municipalities')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('farmer_id', 'machinery_farmer_fk')
                ->references('id')
                ->on('farmers')
                ->nullOnDelete();
            $table->foreign(
                'farmers_cooperative_id',
                'machinery_cooperative_fk'
            )
                ->references('id')
                ->on('farmers_cooperatives')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agricultural_machineries');
    }
};
