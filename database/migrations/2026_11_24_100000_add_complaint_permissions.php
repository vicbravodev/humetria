<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * NOM-035, complaints channel (8.1 b): manage-complaint-channel (owner and hr: activate, designate handlers, read the status)
 * and handle-complaints (owner, hr and committee: the ability to be a handler). Having the permission opens no case: a
 * case opens only to a handler assigned to it.
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'manage-complaint-channel' => ['owner', 'hr'],
        'handle-complaints' => ['owner', 'hr', 'committee'],
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
