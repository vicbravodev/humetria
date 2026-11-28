<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What Humetría tells the companies from the console (a novelty, a maintenance, an incident, a billing matter): a banner in
 * their panel between two moments. Platform data, not a company's: it has no tenant_id. Its state comes from its dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('audience', 10);
            // A key of the plan catalog when the audience is a plan; null when it goes to every company.
            $table->string('plan', 30)->nullable();
            $table->string('title', 120);
            $table->string('message', 500);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            // Null while it is a draft: a draft never reaches a panel.
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index('ends_at');
        });

        DB::statement("alter table platform_announcements add constraint platform_announcements_kind_check check (kind in ('news', 'maintenance', 'incident', 'billing'))");
        DB::statement("alter table platform_announcements add constraint platform_announcements_audience_check check ((audience = 'all' and plan is null) or (audience = 'plan' and plan is not null))");
        DB::statement('alter table platform_announcements add constraint platform_announcements_dates_check check (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcements');
    }
};
