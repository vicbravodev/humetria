<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The superadmin acting as a company owner. Like actor_id it has no foreign
 * key on purpose: deleting a user must not touch audit rows. Adding a column
 * is not blocked by the append-only trigger, which only stops UPDATE, DELETE
 * and TRUNCATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonator_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->dropColumn('impersonator_id');
        });
    }
};
