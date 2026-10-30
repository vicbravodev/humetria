<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who runs the company's evaluations (create, launch, follow and close campaigns): owner and hr. viewer only follows
 * them.
 */
return new class extends Migration
{
    private const array ROLES = ['owner', 'hr'];

    public function up(): void
    {
        $now = now();
        $permission = DB::table('permissions')->insertGetId(['name' => 'manage-campaigns', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->whereNull('tenant_id')->pluck('id') as $role) {
            DB::table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'manage-campaigns')->delete();
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
