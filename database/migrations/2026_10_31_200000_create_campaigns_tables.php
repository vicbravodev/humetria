<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A campaign applies instruments to the people of one work center; each participant holds a one-time link (only its
 * hash) and the state of each guide they were given. Composite foreign keys keep every row inside one company. A work
 * center has at most one NOM-035 evaluation in draft or active at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lets campaign participants reference an employee of the same company (as employees do with work centers).
        Schema::table('employees', function (Blueprint $table) {
            $table->unique(['id', 'tenant_id'], 'employees_id_tenant_unique');
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->string('kind', 30);
            $table->string('status', 10)->default(CampaignStatus::Draft->value);
            $table->jsonb('instruments');
            $table->unsignedInteger('workers')->nullable();
            $table->string('population', 10)->nullable();
            $table->unsignedInteger('sample_size')->nullable();
            $table->jsonb('quotas')->nullable();
            $table->date('closes_on')->nullable();
            $table->timestampTz('launched_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['id', 'tenant_id'], 'campaigns_id_tenant_unique');
            $table->index(['tenant_id', 'work_center_id']);
            $table->index(['status', 'closes_on']);
        });

        $kinds = $this->values(CampaignKind::cases());
        $statuses = $this->values(CampaignStatus::cases());
        $populations = $this->values(Population::cases());

        DB::statement(<<<SQL
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_kind_known CHECK (kind IN ({$kinds})),
                ADD CONSTRAINT campaigns_status_known CHECK (status IN ({$statuses})),
                ADD CONSTRAINT campaigns_population_known CHECK (population IS NULL OR population IN ({$populations})),
                ADD CONSTRAINT campaigns_launched_dated CHECK (status = 'draft' OR (launched_at IS NOT NULL AND closes_on IS NOT NULL)),
                ADD CONSTRAINT campaigns_closed_dated CHECK ((status = 'closed') = (closed_at IS NOT NULL)),
                ADD CONSTRAINT campaigns_sample_sized CHECK ((population = 'sample') = (sample_size IS NOT NULL AND quotas IS NOT NULL))
        SQL);
        DB::statement("CREATE UNIQUE INDEX campaigns_one_open_evaluation ON campaigns (tenant_id, work_center_id) WHERE kind = 'nom035_evaluation' AND status IN ('draft', 'active')");

        Schema::create('campaign_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->uuid('employee_id');
            $table->boolean('in_sample')->default(false);
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestampTz('invited_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->cascadeOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['campaign_id', 'employee_id']);
            $table->unique(['id', 'tenant_id'], 'campaign_participants_id_tenant_unique');
        });

        Schema::create('participant_instruments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('participant_id');
            $table->string('instrument', 40);
            $table->string('status', 10)->default(InstrumentStatus::Pending->value);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['participant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaign_participants')->cascadeOnDelete();
            $table->unique(['participant_id', 'instrument']);
        });

        $instrumentStatuses = $this->values(InstrumentStatus::cases());

        DB::statement(<<<SQL
            ALTER TABLE participant_instruments
                ADD CONSTRAINT participant_instruments_status_known CHECK (status IN ({$instrumentStatuses})),
                ADD CONSTRAINT participant_instruments_completed_dated CHECK ((status = 'completed') = (completed_at IS NOT NULL)),
                ADD CONSTRAINT participant_instruments_cancelled_dated CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_instruments');
        Schema::dropIfExists('campaign_participants');
        Schema::dropIfExists('campaigns');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_id_tenant_unique');
        });
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function values(array $cases): string
    {
        return implode(', ', array_map(fn (BackedEnum $case): string => "'{$case->value}'", $cases));
    }
};
