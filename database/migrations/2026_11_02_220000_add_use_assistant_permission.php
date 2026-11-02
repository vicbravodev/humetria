<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who talks to Humi, the AI assistant: owner, hr and viewer. The Comisión de seguridad e higiene sees only NOM-037
 * (criterio C-25), so it has no Humi.
 */
return new class extends Migration
{
    private const array ROLES = ['owner', 'hr', 'viewer'];

    public function up(): void
    {
        $now = now();
        $permission = DB::table('permissions')->insertGetId(['name' => 'use-assistant', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->whereNull('tenant_id')->pluck('id') as $role) {
            DB::table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'use-assistant')->delete();
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
