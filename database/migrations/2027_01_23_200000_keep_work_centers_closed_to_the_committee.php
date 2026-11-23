<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Comisión never opened the work centers (its routes were only NOM-037, Ley Silla and the channel), so its template
 * does not get view-work-centers: once every route asks for its permission, it would have gained those pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_has_permissions')
            ->whereIn('role_id', DB::table('roles')->where('name', 'committee')->whereNull('tenant_id')->select('id'))
            ->whereIn('permission_id', DB::table('permissions')->where('name', 'view-work-centers')->select('id'))
            ->delete();

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('name', 'committee')->whereNull('tenant_id')->value('id');
        $permission = DB::table('permissions')->where('name', 'view-work-centers')->value('id');

        if ($role !== null && $permission !== null) {
            DB::table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
