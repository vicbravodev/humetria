<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A company names each of its own roles once, whatever the case: the panel and the invitation tell them apart by name. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX roles_tenant_label_unique ON roles (tenant_id, lower(label)) WHERE tenant_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX roles_tenant_label_unique');
    }
};
