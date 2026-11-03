<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * NOM-035 Programa de intervención (num. 8.3 to 8.5): view-interventions (owner, hr and viewer; the viewer reads the
 * Programa but opens no evidence file) and manage-interventions (owner and hr). The Comisión de seguridad e higiene has
 * no part in Chapter 8 of the norm, so it has neither.
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'view-interventions' => ['owner', 'hr', 'viewer'],
        'manage-interventions' => ['owner', 'hr'],
    ];

    public function up(): void
    {
        $now = now();

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
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
