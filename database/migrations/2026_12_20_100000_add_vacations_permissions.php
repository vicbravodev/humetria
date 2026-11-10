<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/** Vacaciones: view-vacations (owner, hr and viewer) and manage-vacations (owner and hr). */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'view-vacations' => ['owner', 'hr', 'viewer'],
        'manage-vacations' => ['owner', 'hr'],
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
