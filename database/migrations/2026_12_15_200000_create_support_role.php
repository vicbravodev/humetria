<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Humetría's second platform role, with no company like the superadmin's: support sees the console and works as an
 * owner with the usual guards, and changes nothing about a company. Assigned by hand to whoever holds it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insert(['name' => 'support', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'support')->whereNull('tenant_id')->delete();
    }
};
