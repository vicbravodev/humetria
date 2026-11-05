<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-037: what a work center declares before its route (rediseño v2, board V2-Aplica): whether someone works away from it
 * more than 40 % of their time (LFT 330-A), with who declared it and when. One per center; declaring again replaces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telework_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->boolean('has_teleworkers');
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->date('declared_on');
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique('work_center_id', 'telework_profiles_work_center_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telework_profiles');
    }
};
