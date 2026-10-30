<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who sees the Guía I of each person and the list of those who need a clinical assessment (num. 5.5, 5.8 c): owner and
 * hr, who channel them. A viewer never sees it.
 */
return new class extends Migration
{
    private const array ROLES = ['owner', 'hr'];

    public function up(): void
    {
        $now = now();
        $permission = DB::table('permissions')->insertGetId(['name' => 'view-trauma-screenings', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->whereNull('tenant_id')->pluck('id') as $role) {
            DB::table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'view-trauma-screenings')->delete();
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
