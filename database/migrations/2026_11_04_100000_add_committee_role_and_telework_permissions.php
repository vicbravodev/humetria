<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * NOM-037 (teletrabajo): the Comisión de seguridad e higiene role, which sees only NOM-037, and three permissions.
 * view-telework: everyone (viewer only sees counts, which the page decides); manage-telework: owner and hr;
 * review-workplaces: committee, owner and hr (editing the list and validating also need to be assigned to the center).
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'view-telework' => ['owner', 'hr', 'viewer', 'committee'],
        'manage-telework' => ['owner', 'hr'],
        'review-workplaces' => ['owner', 'hr', 'committee'],
    ];

    public function up(): void
    {
        $now = now();
        DB::statement('ALTER TABLE tenant_invitations DROP CONSTRAINT tenant_invitations_role_known');
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_known CHECK (role IN ('owner', 'hr', 'viewer', 'committee'))");

        DB::table('roles')->insert(['name' => 'committee', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

            foreach (DB::table('roles')->whereIn('name', $roles)->whereNull('tenant_id')->pluck('id') as $role) {
                DB::table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
            }
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        DB::table('model_has_roles')->whereIn('role_id', DB::table('roles')->where('name', 'committee')->select('id'))->delete();
        DB::table('roles')->where('name', 'committee')->delete();
        DB::statement("DELETE FROM tenant_invitations WHERE role = 'committee'");
        DB::statement('ALTER TABLE tenant_invitations DROP CONSTRAINT tenant_invitations_role_known');
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_known CHECK (role IN ('owner', 'hr', 'viewer'))");
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
