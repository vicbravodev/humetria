<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Actions\CreateEvaluationDraft;
use App\Modules\Campaigns\Actions\DeleteDraft;
use App\Modules\Campaigns\Data\PeopleFilter;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Standing;
use App\Modules\Campaigns\Http\Requests\CreateEvaluationRequest;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\CampaignPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The NOM-035 evaluations of the company in the URL. Every role follows them; only those who manage campaigns change
 * them.
 */
final class CampaignController extends Controller
{
    public function __construct(private readonly CampaignPages $pages) {}

    public function index(Request $request): Response
    {
        return Inertia::render('campaigns/index', [
            ...$this->pages->index(),
            // The work center the new-evaluation assistant chose, asked for by that assistant alone.
            'candidate' => Inertia::optional(fn (): ?array => $this->pages->candidate($request->string('evaluate')->toString())),
        ]);
    }

    public function store(CreateEvaluationRequest $request, mixed $tenant, CreateEvaluationDraft $createDraft): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $user = $request->user();
        abort_if($user === null, 403);

        $campaign = $createDraft->handle($request->workCenterId(), $request->population(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador de evaluación creado.']);

        return redirect()->route('tenant.campaigns.show', [$tenant, $campaign->id]);
    }

    public function show(Request $request, mixed $tenant, string $campaign): Response
    {
        $found = self::find($campaign);

        return Inertia::render('campaigns/show', $this->pages->show($found, $request->user()?->can('manage-campaigns') ?? false, self::peopleFilter($request, $found)));
    }

    /**
     * The people filter from the query string: pending people by default, and anything unknown left out.
     */
    public static function peopleFilter(Request $request, Campaign $campaign): PeopleFilter
    {
        $status = $request->string('status')->toString();
        $guide = $request->string('guide')->toString();
        $area = $request->string('area')->toString();

        return new PeopleFilter(
            standing: $status === 'all' ? null : Standing::tryFrom($status) ?? Standing::Pending,
            guide: in_array($guide, $campaign->instruments, true) ? $guide : null,
            areaId: Str::isUuid($area) ? $area : null,
            search: $request->string('q')->trim()->limit(100, '')->toString(),
        );
    }

    public function destroy(mixed $tenant, string $campaign, DeleteDraft $deleteDraft): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $deleteDraft->handle(self::find($campaign, CampaignStatus::Draft));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador eliminado.']);

        return redirect()->route('tenant.campaigns.index', $tenant);
    }

    /**
     * Looked up here, not by route model binding: binding runs before ResolveTenant, when the company scope cannot be
     * applied yet. Another company's campaign is not found, the same 404 as one that does not exist; one that is not
     * in the expected state is a 409.
     */
    public static function find(string $id, ?CampaignStatus $status = null): Campaign
    {
        $campaign = Campaign::query()->findOrFail($id);
        abort_if($status !== null && $campaign->status !== $status, 409);

        return $campaign;
    }
}
