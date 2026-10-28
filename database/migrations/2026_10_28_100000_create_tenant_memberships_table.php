<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who belongs to a company and with which role. Each company has at most one owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestampsTz(precision: 0);

            $table->unique(['tenant_id', 'user_id'], 'tenant_memberships_tenant_user_unique');
        });

        DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT tenant_memberships_role_known CHECK (role IN ('owner'))");
        DB::statement("CREATE UNIQUE INDEX tenant_memberships_one_owner ON tenant_memberships (tenant_id) WHERE role = 'owner'");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
