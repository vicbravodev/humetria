<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seat report no longer keeps the position of who sent it (finding 15): in a position of two or three people it pointed at
 * them, and fixing a seat needs only the center and what the person wrote (LFPDPPP, proportionality). Dropping the column
 * also erases the positions already kept; going back restores the column empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_reports', function (Blueprint $table) {
            $table->dropForeign(['position_id', 'tenant_id']);
            $table->dropColumn('position_id');
        });
    }

    public function down(): void
    {
        Schema::table('seat_reports', function (Blueprint $table) {
            $table->uuid('position_id')->nullable();
            $table->foreign(['position_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('standing_positions')->restrictOnDelete();
        });
    }
};
