<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\SearchesText;
use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use Carbon\CarbonImmutable;
use Database\Factories\Compensation\BenefitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A benefit the company grants, defined by attributes alone: how it is worked out, who gets it and which bases it forms
 * part of. A base benefit (aguinaldo, vales...) has a base_key and is never archived; one the company defines has none.
 * Its money is in its terms (ContractTerm).
 *
 * sbc_integration "up to a cap": sbc_cap_cents is the cap in cents; a base benefit leaves it empty and the cap is the one the
 * law sets (vales de despensa: 40 % of the daily UMA; premios de asistencia: 10 % of the SBC).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property ?string $base_key
 * @property BenefitKind $kind
 * @property Frequency $frequency
 * @property ?PayGroup $applies_to_group
 * @property list<string> $job_grade_ids empty = every category
 * @property int $min_tenure_months
 * @property SbcIntegration $sbc_integration
 * @property ?int $sbc_cap_cents
 * @property bool $in_payroll_tax
 * @property bool $in_ptu_base
 * @property bool $in_seniority_base
 * @property bool $income_tax_exempt only pointed out, never worked out
 * @property ?int $cap_cents
 * @property ?CarbonImmutable $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'name', 'base_key', 'kind', 'frequency', 'applies_to_group', 'job_grade_ids', 'min_tenure_months', 'sbc_integration', 'sbc_cap_cents', 'in_payroll_tax', 'in_ptu_base', 'in_seniority_base', 'income_tax_exempt', 'cap_cents', 'archived_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(BenefitFactory::class)]
final class Benefit extends Model
{
    /** @use HasFactory<BenefitFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, SearchesText;

    /** @return HasMany<ContractTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(ContractTerm::class);
    }

    public function isBase(): bool
    {
        return $this->base_key !== null;
    }

    public function baseKey(): ?BaseBenefit
    {
        return $this->base_key === null ? null : BaseBenefit::tryFrom($this->base_key);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => BenefitKind::class,
            'frequency' => Frequency::class,
            'applies_to_group' => PayGroup::class,
            'sbc_integration' => SbcIntegration::class,
            'job_grade_ids' => 'array',
            'min_tenure_months' => 'integer',
            'sbc_cap_cents' => 'integer',
            'in_payroll_tax' => 'boolean',
            'in_ptu_base' => 'boolean',
            'in_seniority_base' => 'boolean',
            'income_tax_exempt' => 'boolean',
            'cap_cents' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
