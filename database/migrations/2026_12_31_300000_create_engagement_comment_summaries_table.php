<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SummaryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the AI said about the comments of a survey, made once when it closed: one row for the comments about the company
 * (`manager_group_id` null) and one for each group of comments of a manager. Only themes with their counts are kept, never
 * a comment. `manager_group_id` names the group of a manager (an employee id), not who wrote: it is not a key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_comment_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('survey_id');
            $table->uuid('manager_group_id')->nullable();
            $table->string('status', 10);
            $table->jsonb('themes')->nullable();
            $table->date('generated_on')->nullable();
            $table->timestampsTz();

            $table->foreign(['survey_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('engagement_surveys')->restrictOnDelete();
        });

        $statuses = implode(', ', array_map(fn (SummaryStatus $case): string => "'{$case->value}'", SummaryStatus::cases()));

        DB::statement("ALTER TABLE engagement_comment_summaries ADD CONSTRAINT engagement_comment_summaries_status_known CHECK (status IN ({$statuses}))");
        DB::statement("ALTER TABLE engagement_comment_summaries ADD CONSTRAINT engagement_comment_summaries_ready_themed CHECK ((status = 'ready') = (themes IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX engagement_comment_summaries_one_per_group ON engagement_comment_summaries (survey_id, manager_group_id) WHERE manager_group_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX engagement_comment_summaries_one_for_company ON engagement_comment_summaries (survey_id) WHERE manager_group_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_comment_summaries');
    }
};
