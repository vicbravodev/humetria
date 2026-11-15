<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\AccountCompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which account pays a company, from a day to a day. The one without an end is the current payer, and the database lets
 * a company have only one. Read across companies by the console, so it has a tenant_id but no per-company scope.
 *
 * @property string $id
 * @property string $billing_account_id
 * @property string $tenant_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 */
#[Fillable(['billing_account_id', 'tenant_id', 'starts_on', 'ends_on'])]
#[UseFactory(AccountCompanyFactory::class)]
final class AccountCompany extends Model
{
    /** @use HasFactory<AccountCompanyFactory> */
    use HasFactory, HasVersion4Uuids;

    protected $table = 'billing_account_tenants';

    /**
     * @return BelongsTo<BillingAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id');
    }

    /**
     * The links that are the current payer of their company.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('ends_on');
    }

    /**
     * The links that paid the company during a period [start, end): they began before it ended and had not ended when it began.
     * A transfer happens on a cut, so a month has one.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCovering(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $query->where('starts_on', '<', $end->toDateString())->where(fn (Builder $open) => $open->whereNull('ends_on')->orWhere('ends_on', '>', $start->toDateString()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
