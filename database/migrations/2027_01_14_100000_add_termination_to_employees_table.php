<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The day a person's work relationship ended and why. The date is the person's own day (Mexico City), not the moment
 * HR typed it. Whoever left before this had none recorded: their date is the day they were deactivated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->date('terminated_on')->nullable();
            $table->string('deactivation_reason', 20)->nullable();
        });

        DB::statement("UPDATE employees SET terminated_on = (deactivated_at AT TIME ZONE 'America/Mexico_City')::date WHERE deactivated_at IS NOT NULL");
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_deactivation_reason_check CHECK (deactivation_reason IN ('resignation', 'dismissal', 'contract_end', 'mutual_agreement', 'death', 'other'))");
        // Only somebody who left has a departure; those deactivated by hand in older data may have no date.
        DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_termination_requires_deactivation_check CHECK ((terminated_on IS NULL AND deactivation_reason IS NULL) OR deactivated_at IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['terminated_on', 'deactivation_reason']);
        });
    }
};
