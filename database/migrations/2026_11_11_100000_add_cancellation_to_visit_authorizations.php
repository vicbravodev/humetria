<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A visit authorization nobody gave can be cancelled (never deleted), so it stops blocking a new request. And a person's
 * confirmation may come without a readable address, so the address is no longer tied to the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_authorizations', function (Blueprint $table) {
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE visit_authorizations DROP CONSTRAINT visit_authorizations_confirmation_pair');
        DB::statement('ALTER TABLE visit_authorizations ADD CONSTRAINT visit_authorizations_ip_needs_confirmation CHECK (confirmed_ip IS NULL OR confirmed_at IS NOT NULL)');
        DB::statement('ALTER TABLE visit_authorizations ADD CONSTRAINT visit_authorizations_cancellation_pair CHECK ((cancelled_at IS NULL) = (cancelled_by IS NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE visit_authorizations DROP CONSTRAINT visit_authorizations_cancellation_pair');
        DB::statement('ALTER TABLE visit_authorizations DROP CONSTRAINT visit_authorizations_ip_needs_confirmation');
        Schema::table('visit_authorizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });
    }
};
