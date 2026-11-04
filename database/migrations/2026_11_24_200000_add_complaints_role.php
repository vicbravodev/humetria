<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * NOM-035, complaints channel: the role of the outside handler (a lawyer, a trusted person, a union representative), who
 * can be designated to handle complaints and sees nothing else of the platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::statement('ALTER TABLE tenant_invitations DROP CONSTRAINT tenant_invitations_role_known');
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_known CHECK (role IN ('owner', 'hr', 'viewer', 'committee', 'complaints'))");

        $role = DB::table('roles')->insertGetId(['name' => 'complaints', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        $permission = DB::table('permissions')->where('name', 'handle-complaints')->value('id');
        DB::table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('model_has_roles')->whereIn('role_id', DB::table('roles')->where('name', 'complaints')->select('id'))->delete();
        DB::table('roles')->where('name', 'complaints')->delete();
        DB::statement("DELETE FROM tenant_invitations WHERE role = 'complaints'");
        DB::statement('ALTER TABLE tenant_invitations DROP CONSTRAINT tenant_invitations_role_known');
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_known CHECK (role IN ('owner', 'hr', 'viewer', 'committee'))");
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
