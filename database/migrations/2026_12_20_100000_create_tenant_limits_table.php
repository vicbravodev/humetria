<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a company may have of each resource its plan limits (people, work centers) and how much it has: the count that a
        // reservation moves with one conditional UPDATE, so two requests for the last place cannot both pass. A company with no row
        // for a resource has no limit on it. `used` may be above `amount` (a company that already had more when its plan was set):
        // it keeps what it has and only cannot add.
        Schema::create('tenant_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('resource', 20);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('used')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'resource']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_limits
                ADD CONSTRAINT tenant_limits_resource_known CHECK (resource IN ('seats', 'centers')),
                ADD CONSTRAINT tenant_limits_amount_not_negative CHECK (amount >= 0),
                ADD CONSTRAINT tenant_limits_used_not_negative CHECK (used >= 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_limits');
    }
};
