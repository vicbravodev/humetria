<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Actions\SetTenantAiSettings;
use App\Modules\Assistant\Http\Requests\TenantAiSettingsRequest;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Assistant\Support\MonthlyUsage;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** What the superadmin sees and sets about Humi for one company, from the console of companies. */
final readonly class TenantSettingsController
{
    public function __construct(private Companies $companies, private MonthlyUsage $usage) {}

    public function show(string $tenant): JsonResponse
    {
        Gate::authorize('manage-tenants');
        $this->enter($tenant);
        $settings = TenantAiSettings::query()->first();

        return response()->json([
            'enabled' => $settings->enabled ?? true,
            'monthly_token_budget' => $settings->monthly_token_budget ?? null,
            'default_monthly_token_budget' => config()->integer('humi.monthly_token_budget'),
            'used_tokens' => $this->usage->tokens(),
        ]);
    }

    public function update(TenantAiSettingsRequest $request, string $tenant, SetTenantAiSettings $set): Response
    {
        try {
            $set->handle($tenant, $request->enabled(), $request->budget());
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response()->noContent();
    }

    private function enter(string $tenant): void
    {
        try {
            $this->companies->enter($tenant);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
