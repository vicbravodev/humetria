<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The funnel of a demo request: two more stages (a demo scheduled, a trial running), the person of the staff who owns it
 * and its next step. If that person's account goes, the request stays and only the owner is cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE demo_requests DROP CONSTRAINT demo_requests_status_valid');
        DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_status_valid CHECK (status IN ('new', 'contacted', 'scheduled', 'trial', 'converted', 'discarded'))");

        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('next_step', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('next_step');
        });

        DB::statement('ALTER TABLE demo_requests DROP CONSTRAINT demo_requests_status_valid');
        DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_status_valid CHECK (status IN ('new', 'contacted', 'converted', 'discarded'))");
    }
};
