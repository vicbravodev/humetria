<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An invitation names its role by id (a template or one of the company's own) and may bring permissions besides it.
 * The role can only go away once no open invitation names it: a used or cancelled one is left without it. The owner's
 * indexes name the owner template by its id, which a partial index has to spell out.
 */
return new class extends Migration
{
    public function up(): void
    {
        $owner = $this->ownerRoleId();

        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('tenant_id')->constrained('roles')->nullOnDelete();
            $table->jsonb('extra_permissions')->default('[]');
        });

        DB::statement('UPDATE tenant_invitations SET role_id = roles.id FROM roles WHERE roles.name = tenant_invitations.role AND roles.tenant_id IS NULL');
        DB::statement('DROP INDEX tenant_invitations_one_open_owner');
        DB::statement('DROP INDEX tenant_invitations_one_open_per_email');

        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->dropColumn('role');
        });

        DB::statement('ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_open_has_role CHECK (role_id IS NOT NULL OR accepted_at IS NOT NULL OR cancelled_at IS NOT NULL)');
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_extra_permissions_list CHECK (jsonb_typeof(extra_permissions) = 'array')");
        DB::statement("CREATE UNIQUE INDEX tenant_invitations_one_open_owner ON tenant_invitations (tenant_id) WHERE role_id = {$owner} AND accepted_at IS NULL AND cancelled_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX tenant_invitations_one_open_per_email ON tenant_invitations (tenant_id, email) WHERE role_id <> {$owner} AND accepted_at IS NULL AND cancelled_at IS NULL");
        DB::statement('CREATE INDEX tenant_invitations_role_id_index ON tenant_invitations (role_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX tenant_invitations_one_open_owner');
        DB::statement('DROP INDEX tenant_invitations_one_open_per_email');

        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->string('role', 20)->nullable();
        });

        // Invitations to a role of the company's own have no place in the old column.
        DB::statement("UPDATE tenant_invitations SET role = roles.name FROM roles WHERE roles.id = tenant_invitations.role_id AND roles.name IN ('owner', 'hr', 'viewer', 'committee', 'complaints')");
        DB::statement('DELETE FROM tenant_invitations WHERE role IS NULL');

        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('extra_permissions');
        });

        DB::statement('ALTER TABLE tenant_invitations ALTER COLUMN role SET NOT NULL');
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_known CHECK (role IN ('owner', 'hr', 'viewer', 'committee', 'complaints'))");
        DB::statement("CREATE UNIQUE INDEX tenant_invitations_one_open_owner ON tenant_invitations (tenant_id) WHERE role = 'owner' AND accepted_at IS NULL AND cancelled_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX tenant_invitations_one_open_per_email ON tenant_invitations (tenant_id, email) WHERE role <> 'owner' AND accepted_at IS NULL AND cancelled_at IS NULL");
    }

    private function ownerRoleId(): int
    {
        $id = DB::table('roles')->where('name', 'owner')->whereNull('tenant_id')->value('id');

        return is_int($id) ? $id : throw new RuntimeException('The owner role is missing.');
    }
};
