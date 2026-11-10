<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\CommitmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the company commits to do after a closed survey, for the whole company or for one group of its results («Nos
 * dijeron / Haremos»). The group is named by id with no foreign key: a work center or an area that is archived later must
 * not erase the promise, and a manager group is an employee id that is not a key either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_commitments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('survey_id');
            $table->string('group_type', 12);
            $table->uuid('group_id')->nullable();
            $table->string('driver', 20)->nullable();
            $table->string('heard', 500);
            $table->string('will_do', 500);
            $table->string('owner_name', 120);
            $table->date('due_on');
            $table->string('status', 12)->default(CommitmentStatus::Open->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['survey_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('engagement_surveys')->cascadeOnDelete();
            $table->index(['survey_id', 'group_type', 'group_id']);
        });

        $groups = implode(', ', array_map(fn (CommitmentGroup $case): string => "'{$case->value}'", CommitmentGroup::cases()));
        $statuses = implode(', ', array_map(fn (CommitmentStatus $case): string => "'{$case->value}'", CommitmentStatus::cases()));

        DB::statement(<<<SQL
            ALTER TABLE engagement_commitments
                ADD CONSTRAINT engagement_commitments_group_known CHECK (group_type IN ({$groups})),
                ADD CONSTRAINT engagement_commitments_status_known CHECK (status IN ({$statuses})),
                ADD CONSTRAINT engagement_commitments_group_id_matches CHECK ((group_type = 'company') = (group_id IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_commitments');
    }
};
