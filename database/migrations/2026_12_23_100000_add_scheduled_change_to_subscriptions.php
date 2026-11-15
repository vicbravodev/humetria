<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a reduction (a smaller plan, fewer people, fewer centers) will be at the next cut: increases apply at once, reductions
        // wait for the cut without credit. A null is "no change in that"; the date says when, and is set exactly when something waits.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('pending_plan', 20)->nullable();
            $table->string('pending_profile', 20)->nullable();
            $table->unsignedInteger('pending_seats')->nullable();
            $table->unsignedInteger('pending_centers')->nullable();
            $table->date('pending_effective_on')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_pending_has_a_day CHECK (
                    (pending_effective_on IS NULL) = (pending_plan IS NULL AND pending_profile IS NULL AND pending_seats IS NULL AND pending_centers IS NULL)
                ),
                ADD CONSTRAINT subscriptions_pending_profile_with_plan CHECK ((pending_plan IS NULL) = (pending_profile IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['pending_plan', 'pending_profile', 'pending_seats', 'pending_centers', 'pending_effective_on']);
        });
    }
};
