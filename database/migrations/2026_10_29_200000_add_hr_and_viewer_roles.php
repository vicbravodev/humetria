<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Besides its owner, a company has people in RH (hr) and people who only look (viewer). A company still has one
 * open owner invitation at most; member invitations are open at most once per email.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tenant_memberships', 'tenant_invitations'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_role_known");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_role_known CHECK (role IN ('owner', 'hr', 'viewer'))");
        }

        DB::statement('DROP INDEX tenant_invitations_one_open');
        DB::statement("CREATE UNIQUE INDEX tenant_invitations_one_open_owner ON tenant_invitations (tenant_id) WHERE role = 'owner' AND accepted_at IS NULL AND cancelled_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX tenant_invitations_one_open_per_email ON tenant_invitations (tenant_id, email) WHERE role <> 'owner' AND accepted_at IS NULL AND cancelled_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX tenant_invitations_one_open_per_email');
        DB::statement('DROP INDEX tenant_invitations_one_open_owner');
        DB::statement('CREATE UNIQUE INDEX tenant_invitations_one_open ON tenant_invitations (tenant_id, role) WHERE accepted_at IS NULL AND cancelled_at IS NULL');

        foreach (['tenant_memberships', 'tenant_invitations'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_role_known");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_role_known CHECK (role IN ('owner'))");
        }
    }
};
