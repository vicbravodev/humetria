<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * NOM-035 policy and disclosure (num. 5.1, 5.7 and 7.8): view-disclosure (owner, hr and viewer; the viewer reads the board and
 * the counts, never the names of who has not confirmed nor the link) and manage-disclosure (owner and hr). The Comisión de
 * seguridad e higiene and the handlers of complaints have neither.
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'view-disclosure' => ['owner', 'hr', 'viewer'],
        'manage-disclosure' => ['owner', 'hr'],
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
