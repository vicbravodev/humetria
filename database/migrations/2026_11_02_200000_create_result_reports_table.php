<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What HR fills in for the report of an evaluation (num. 7.7): objective, activities, conclusions, recommendations and
 * who is responsible. One per campaign; the rest of the report comes from the evaluation itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->text('objective');
            $table->text('activities');
            $table->text('conclusions');
            $table->text('recommendations');
            $table->string('responsible_name', 150);
            $table->string('responsible_license', 20)->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->cascadeOnDelete();
            $table->unique('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_reports');
    }
};
