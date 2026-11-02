<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ley Silla: view-seating (everyone; viewer only sees counts, which the page decides), manage-seating (owner and hr) and
 * assess-seating (owner, hr and committee; signing also needs the person to be assigned to the center).
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'view-seating' => ['owner', 'hr', 'viewer', 'committee'],
        'manage-seating' => ['owner', 'hr'],
        'assess-seating' => ['owner', 'hr', 'committee'],
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
