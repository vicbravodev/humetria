<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\TenureBand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The people a survey was sent to: each holds a one-time link (only its hash is kept) and the coarse group they were
 * invited in (work center, area, tenure range, manager groups), fixed at launch so a later move changes nothing. Only
 * `answered` says that someone answered: no date, and no key from here to the answers. The row has no `updated_at`,
 * so marking it leaves no hour behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('survey_id');
            $table->uuid('employee_id');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->string('invitation_status', 20)->nullable();
            $table->uuid('invitation_batch')->nullable();
            $table->timestampTz('invited_at')->nullable();
            $table->boolean('answered')->default(false);
            $table->boolean('cancelled')->default(false);
            $table->uuid('work_center_id')->nullable();
            $table->uuid('area_id')->nullable();
            $table->string('tenure_band', 10)->nullable();
            // They name the group of a manager (an employee id) but are not keys: the group is not who answers.
            $table->uuid('manager_group_id')->nullable();
            $table->uuid('comment_group_id')->nullable();
            $table->uuid('about_manager_id')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->foreign(['survey_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('engagement_surveys')->cascadeOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['area_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('areas')->restrictOnDelete();
            $table->foreign(['about_manager_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['survey_id', 'employee_id']);
            $table->unique(['id', 'tenant_id'], 'engagement_invitations_id_tenant_unique');
        });

        $statuses = $this->values(InvitationStatus::cases());
        $bands = $this->values(TenureBand::cases());

        DB::statement(<<<SQL
            ALTER TABLE engagement_invitations
                ADD CONSTRAINT engagement_invitations_status_known CHECK (invitation_status IS NULL OR invitation_status IN ({$statuses})),
                ADD CONSTRAINT engagement_invitations_tenure_known CHECK (tenure_band IS NULL OR tenure_band IN ({$bands}))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_invitations');
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function values(array $cases): string
    {
        return implode(', ', array_map(fn (BackedEnum $case): string => "'{$case->value}'", $cases));
    }
};
