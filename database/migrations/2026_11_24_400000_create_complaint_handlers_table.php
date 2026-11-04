<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035, complaints channel: the people the company designates to handle complaints, for the whole company or one work
 * center. A designation ends; it is never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_handlers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('work_center_id')->nullable();
            $table->foreignId('designated_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('designated_at');
            $table->timestampTz('ended_at')->nullable();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['id', 'tenant_id'], 'complaint_handlers_id_tenant_unique');
            $table->index(['tenant_id', 'user_id']);
        });

        // One open designation per person and scope (the whole company counts as a scope of its own).
        DB::statement("CREATE UNIQUE INDEX complaint_handlers_open_unique ON complaint_handlers (tenant_id, user_id, COALESCE(work_center_id, '00000000-0000-0000-0000-000000000000'::uuid)) WHERE ended_at IS NULL");
        DB::statement('ALTER TABLE complaint_handlers ADD CONSTRAINT complaint_handlers_ended_after_designated CHECK (ended_at IS NULL OR ended_at >= designated_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_handlers');
    }
};
