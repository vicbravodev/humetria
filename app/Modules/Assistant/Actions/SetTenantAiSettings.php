<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Actions;

use App\Actions\RecordAudit;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * What the superadmin decides about Humi for a company: whether it is on and its monthly budget of tokens (none: the
 * platform's default). Works from the console, outside any company, so it enters the company it changes.
 */
final readonly class SetTenantAiSettings
{
    public function __construct(private Companies $companies, private RecordAudit $recordAudit) {}

    /**
     * @throws InvalidArgumentException when no company has that id.
     */
    public function handle(string $tenantId, bool $enabled, ?int $monthlyTokenBudget): TenantAiSettings
    {
        $this->companies->enter($tenantId);

        return DB::transaction(function () use ($tenantId, $enabled, $monthlyTokenBudget): TenantAiSettings {
            $settings = TenantAiSettings::query()->updateOrCreate(['tenant_id' => $tenantId], ['enabled' => $enabled, 'monthly_token_budget' => $monthlyTokenBudget]);
            $change = ['enabled' => $enabled, 'monthly_token_budget' => $monthlyTokenBudget];

            Log::info('assistant.settings_changed', ['tenant_id' => $tenantId, ...$change]);
            $this->recordAudit->handle('assistant.settings_changed', null, $change);

            return $settings;
        });
    }
}
