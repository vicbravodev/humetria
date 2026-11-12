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
        // The modules a company has, and where each comes from: its plan, a person's grant (with a reason and maybe an end), the
        // legacy ones a plan is taking away, or a person's revocation. Switching off is an end date, never a delete.
        // A company with no 'plan' row keeps every module that exists in code: nothing is written for it, so nothing is lost.
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('module', 30);
            $table->string('source', 10);
            $table->text('reason')->nullable();
            // The first moment the row no longer counts; null never ends.
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'module', 'source']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_modules
                ADD CONSTRAINT tenant_modules_source_known CHECK (source IN ('plan', 'legacy', 'grant', 'revoke')),
                ADD CONSTRAINT tenant_modules_manual_has_reason CHECK (source NOT IN ('grant', 'revoke') OR (reason IS NOT NULL AND btrim(reason) <> ''))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};
