<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A climate or pulse survey of a company: the questions it was launched with (refs of the item bank, so a later
 * rewording never changes what was asked), its deadline and, once closed, the priorities worked out. A company has at
 * most one survey of each kind in draft or active at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('status', 10)->default(SurveyStatus::Draft->value);
            $table->string('name', 120);
            $table->string('bank_version', 10);
            $table->jsonb('items');
            $table->date('closes_on')->nullable();
            $table->timestampTz('launched_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->unsignedInteger('invited')->nullable();
            $table->jsonb('priorities')->nullable();
            $table->boolean('sensitive_mentions')->default(false);
            $table->timestampTz('results_published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['id', 'tenant_id'], 'engagement_surveys_id_tenant_unique');
            $table->index(['tenant_id', 'kind']);
            $table->index(['status', 'closes_on']);
        });

        $kinds = $this->values(SurveyKind::cases());
        $statuses = $this->values(SurveyStatus::cases());

        DB::statement(<<<SQL
            ALTER TABLE engagement_surveys
                ADD CONSTRAINT engagement_surveys_kind_known CHECK (kind IN ({$kinds})),
                ADD CONSTRAINT engagement_surveys_status_known CHECK (status IN ({$statuses})),
                ADD CONSTRAINT engagement_surveys_launched_dated CHECK (status = 'draft' OR (launched_at IS NOT NULL AND closes_on IS NOT NULL)),
                ADD CONSTRAINT engagement_surveys_closed_dated CHECK ((status = 'closed') = (closed_at IS NOT NULL))
        SQL);
        DB::statement("CREATE UNIQUE INDEX engagement_surveys_one_open ON engagement_surveys (tenant_id, kind) WHERE status IN ('draft', 'active')");
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_surveys');
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function values(array $cases): string
    {
        return implode(', ', array_map(fn (BackedEnum $case): string => "'{$case->value}'", $cases));
    }
};
