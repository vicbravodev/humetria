<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company roles move to spatie/laravel-permission with teams, a company being the team: the roles and what they allow
 * are Spatie's, assigned with the company's tenant_id; the superadmin keeps its role with no company. A membership
 * now only says who belongs to which company. The database still allows a single owner per company.
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'owner' => ['manage-organization', 'manage-members'],
        'hr' => ['manage-organization'],
        'viewer' => [],
    ];

    public function up(): void
    {
        $this->addTeams();
        $ids = $this->createRoles();

        // Each existing membership's role becomes the same Spatie role inside its company.
        DB::statement(<<<'SQL'
            INSERT INTO model_has_roles (role_id, model_type, model_id, tenant_id)
            SELECT roles.id, ?, tenant_memberships.user_id, tenant_memberships.tenant_id
            FROM tenant_memberships JOIN roles ON roles.name = tenant_memberships.role AND roles.guard_name = 'web'
        SQL, [(new User)->getMorphClass()]);

        DB::statement("CREATE UNIQUE INDEX model_has_roles_one_owner_per_company ON model_has_roles (tenant_id) WHERE role_id = {$ids['owner']}");

        DB::statement('DROP INDEX tenant_memberships_one_owner');
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->string('role', 20)->default('owner');
        });
        DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT tenant_memberships_role_known CHECK (role IN ('owner', 'hr', 'viewer'))");
        DB::statement(<<<'SQL'
            UPDATE tenant_memberships SET role = roles.name
            FROM model_has_roles JOIN roles ON roles.id = model_has_roles.role_id
            WHERE model_has_roles.tenant_id = tenant_memberships.tenant_id AND model_has_roles.model_id = tenant_memberships.user_id
        SQL);
        DB::statement("CREATE UNIQUE INDEX tenant_memberships_one_owner ON tenant_memberships (tenant_id) WHERE role = 'owner'");

        DB::table('model_has_roles')->whereNotNull('tenant_id')->delete();
        DB::table('roles')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('name', ['manage-organization', 'manage-members'])->delete();

        foreach (['model_has_roles' => 'role', 'model_has_permissions' => 'permission'] as $table => $key) {
            DB::statement("DROP INDEX {$table}_unique");
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('tenant_id'));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$key}_model_type_primary PRIMARY KEY ({$key}_id, model_id, model_type)");
        }

        DB::statement('DROP INDEX roles_tenant_name_guard_unique');
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique(['name', 'guard_name']);
        });
    }

    /**
     * Spatie's add_teams_fields, with the company's UUID as the team key. A role or an assignment with no company is
     * the platform's: the unique indexes treat that missing company as one value.
     */
    private function addTeams(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['name', 'guard_name']);
        });
        DB::statement('CREATE UNIQUE INDEX roles_tenant_name_guard_unique ON roles (tenant_id, name, guard_name) NULLS NOT DISTINCT');

        foreach (['model_has_roles' => 'role', 'model_has_permissions' => 'permission'] as $table => $key) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $key) {
                $blueprint->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $blueprint->dropPrimary("{$table}_{$key}_model_type_primary");
                $blueprint->index('tenant_id');
            });
            DB::statement("CREATE UNIQUE INDEX {$table}_unique ON {$table} (tenant_id, {$key}_id, model_id, model_type) NULLS NOT DISTINCT");
        }
    }

    /**
     * @return array<string, int>
     */
    private function createRoles(): array
    {
        $now = now();

        foreach (array_unique(array_merge(...array_values(self::PERMISSIONS))) as $permission) {
            DB::table('permissions')->insert(['name' => $permission, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        $ids = [];

        foreach (self::PERMISSIONS as $role => $permissions) {
            $ids[$role] = DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

            foreach ($permissions as $permission) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $ids[$role],
                    'permission_id' => DB::table('permissions')->where('name', $permission)->value('id'),
                ]);
            }
        }

        return $ids;
    }
};
