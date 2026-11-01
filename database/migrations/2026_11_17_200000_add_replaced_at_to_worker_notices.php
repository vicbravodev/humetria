<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the replacement of a permanent change of address was applied (the old workplaces retired): it happens once per
 * notice, so a later validation of the new workplace retires nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_notices', function (Blueprint $table) {
            $table->timestampTz('replaced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('worker_notices', function (Blueprint $table) {
            $table->dropColumn('replaced_at');
        });
    }
};
