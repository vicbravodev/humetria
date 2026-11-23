<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Company roles of their own get a label and an informative origin. The pages that had no permission (dashboard, work
 * centers, NOM-035 route) get one, granted to exactly the template roles that open them today.
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'view-dashboard' => ['owner', 'hr', 'viewer', 'committee', 'complaints'],
        'view-work-centers' => ['owner', 'hr', 'viewer', 'committee'],
        'view-nom035-route' => ['owner', 'hr', 'viewer'],
    ];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('label')->nullable();
            $table->string('based_on')->nullable();
        });

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

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['label', 'based_on']);
        });

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
