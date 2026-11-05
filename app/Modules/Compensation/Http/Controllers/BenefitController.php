<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ArchiveBenefit;
use App\Modules\Compensation\Actions\CountBenefits;
use App\Modules\Compensation\Actions\ListBenefits;
use App\Modules\Compensation\Actions\RestoreBenefit;
use App\Modules\Compensation\Actions\SaveBenefit;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Http\Requests\BenefitRequest;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\BaseCatalog;
use App\Modules\Compensation\Support\BenefitRows;
use App\Modules\Compensation\Support\ContractStatus;
use App\Modules\Compensation\Support\PremiumRate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Prestaciones»: the catalog of what the contract grants (the base benefits and the ones the company makes up) with the
 * terms in force. The base ones are never deleted or archived, only their terms change; the ones made up are archived.
 */
final class BenefitController extends Controller
{
    public function index(Request $request, BaseCatalog $catalog, ListBenefits $list, CountBenefits $count, BenefitRows $rows, ContractStatus $status): Response
    {
        Gate::authorize('view-compensation');

        $catalog->ensure();
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $archived = $request->string('status')->toString() === 'archived';
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();

        return Inertia::render('compensation/benefits', [
            'benefits' => $rows->through($list->handle($search, $archived), $today),
            'counts' => $count->handle(),
            'filters' => ['q' => $search, 'status' => $archived ? 'archived' : 'active'],
            'kinds' => array_map(fn (BenefitKind $kind): array => ['value' => $kind->value, 'label' => $kind->label(), 'unit' => $kind->unit()], BenefitKind::cases()),
            'frequencies' => array_map(fn (Frequency $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()], Frequency::cases()),
            'groups' => array_map(fn (PayGroup $group): array => ['value' => $group->value, 'label' => $group->label()], PayGroup::cases()),
            'sbc_integrations' => array_map(fn (SbcIntegration $integration): array => ['value' => $integration->value, 'label' => $integration->label()], SbcIntegration::cases()),
            'risk_premium' => $this->riskPremium($today),
            'status' => $status->handle($today),
        ]);
    }

    public function store(BenefitRequest $request, SaveBenefit $save): RedirectResponse
    {
        $save->handle($request->toData(), $request->firstTerm());

        return $this->done('Prestación creada.');
    }

    public function update(BenefitRequest $request, mixed $tenant, string $benefit, SaveBenefit $save): RedirectResponse
    {
        $save->handle($request->toData());

        return $this->done('Prestación actualizada.');
    }

    public function archive(mixed $tenant, string $benefit, ArchiveBenefit $archive): RedirectResponse
    {
        Gate::authorize('manage-compensation');
        $archive->handle(Benefit::query()->findOrFail($benefit));

        return $this->done('Prestación archivada.');
    }

    public function restore(mixed $tenant, string $benefit, RestoreBenefit $restore): RedirectResponse
    {
        Gate::authorize('manage-compensation');
        $restore->handle(Benefit::query()->findOrFail($benefit));

        return $this->done('Prestación restaurada.');
    }

    // The $tenant parameters receive the company Tenancy resolved from the URL; they are only there to keep the route
    // parameters in order.

    /**
     * The premium in force today, as the person types it.
     *
     * @return ?array{rate: string, effective_from: string}
     */
    private function riskPremium(CarbonImmutable $today): ?array
    {
        $premium = WorkRiskPremium::query()->where('effective_from', '<=', $today->toDateString())->orderByDesc('effective_from')->first();

        return $premium === null ? null : ['rate' => PremiumRate::format($premium->rate_scaled), 'effective_from' => $premium->effective_from->toDateString()];
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
