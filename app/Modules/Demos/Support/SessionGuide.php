<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Http\Impersonation;
use App\Models\User;
use App\Modules\Demos\Contracts\DemoGuide;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The guide, computed from the data every time it is read: only the skipped steps and the visited pages live on the
 * session. One instance answers one request: who presents and the session in course are read once.
 *
 * @phpstan-import-type Guide from DemoGuide
 * @phpstan-import-type GuideStep from DemoGuide
 */
final class SessionGuide implements DemoGuide
{
    private ?string $demoId = null;

    private ?bool $presenter = null;

    private DemoSession|false|null $session = null;

    public function __construct(
        private readonly Impersonation $impersonation,
        private readonly DemoCompanies $demos,
        private readonly Companies $companies,
        private readonly TourCatalog $catalog,
    ) {}

    public function isPresenter(): bool
    {
        return $this->presenter ??= $this->presenting();
    }

    public function recordVisit(string $routeName): void
    {
        if (in_array($routeName, $this->catalog->visits(), true)) {
            $this->session()?->recordVisit($routeName);
        }
    }

    public function forCurrentRequest(): ?array
    {
        $session = $this->session();

        if ($session === null || $this->demoId === null) {
            return null;
        }

        // The poll comes from outside the company's routes: it works in the demo, the only company the presenter may enter.
        $slug = ($this->demos->isCurrent() ? $this->companies->current() : $this->companies->enter($this->demoId))->slug;
        $tours = $this->catalog->toursOf($session);
        $states = $this->catalog->states($tours, $session);
        $steps = $this->catalog->for($tours);
        $passed = fn (TourStep $step): bool => $states[$step->key]->done || in_array($step->key, $session->skipped_steps, true);
        $current = array_find($steps, fn (TourStep $step): bool => ! $passed($step));
        // The step the guide showed before, once it is done by the data (a skip is no achievement), is celebrated once.
        $left = $session->moveGuideTo($current?->key);
        $justDone = $left !== null && ($states[$left]->done ?? false) && ! in_array($left, $session->skipped_steps, true)
            ? $this->catalog->step($session, $left)?->title
            : null;

        return [
            'tours' => array_map(fn (TourKey $tour): array => $this->tour($tour, $steps, $passed), array_values(array_unique(array_map(fn (TourStep $step): TourKey => $step->tour, $steps), SORT_REGULAR))),
            'current' => $current === null ? null : $this->step($current, $states[$current->key], $steps, $session, $slug),
            'finished' => $current === null,
            'justDone' => $justDone,
        ];
    }

    private function presenting(): bool
    {
        $impersonatorId = $this->impersonation->impersonatorId();
        $tenantId = $this->impersonation->tenantId();

        if ($impersonatorId === null || $tenantId === null) {
            return false;
        }

        $this->demoId = $this->demos->id();
        $impersonator = $tenantId === $this->demoId ? User::query()->find($impersonatorId) : null;

        // The gate of the room decides, for the one behind the impersonation: the impersonated owner or HR never passes it.
        return $impersonator !== null && Gate::forUser($impersonator)->allows('manage-demo');
    }

    private function session(): ?DemoSession
    {
        if ($this->session === null) {
            $this->session = ($this->isPresenter() ? DemoSession::active() : null) ?? false;
        }

        return $this->session === false ? null : $this->session;
    }

    /**
     * @param  list<TourStep>  $steps
     * @param  callable(TourStep): bool  $passed
     * @return array{key: string, title: string, done: int, total: int}
     */
    private function tour(TourKey $tour, array $steps, callable $passed): array
    {
        $own = array_filter($steps, fn (TourStep $step): bool => $step->tour === $tour);

        return ['key' => $tour->value, 'title' => $tour->title(), 'done' => count(array_filter($own, $passed)), 'total' => count($own)];
    }

    /**
     * @param  list<TourStep>  $steps
     * @return GuideStep
     */
    private function step(TourStep $step, StepState $state, array $steps, DemoSession $session, string $slug): array
    {
        $own = array_values(array_filter($steps, fn (TourStep $other): bool => $other->tour === $step->tour));
        $index = array_search($step, $own, true);

        return [
            'key' => $step->key,
            'tour' => $step->tour->title(),
            'title' => $step->title,
            'todo' => $step->todo,
            'why' => $step->why,
            'kind' => $step->kind,
            'href' => $this->href($step, $own, $session, $slug),
            'target' => $step->target,
            'ask' => $step->ask,
            'live' => $state->live,
            'index' => $index === false ? 1 : $index + 1,
            'total' => count($own),
        ];
    }

    /**
     * The step's page in the demo; while it needs what does not exist yet (the evaluation not yet created), the first page
     * of its tour that needs nothing more, and the dashboard as the last resort.
     *
     * @param  list<TourStep>  $own  the steps of its tour
     */
    private function href(TourStep $step, array $own, DemoSession $session, string $slug): string
    {
        foreach ([$step->route, ...array_map(fn (TourStep $other): string => $other->route, $own)] as $name) {
            $parameters = ['tenant' => $slug];

            foreach (Route::getRoutes()->getByName($name)?->parameterNames() ?? [] as $parameter) {
                if (is_string($parameter)) {
                    $parameters[$parameter] ??= $this->catalog->parameter($parameter, $session);
                }
            }

            if (! in_array(null, $parameters, true)) {
                return route($name, $parameters);
            }
        }

        return route('tenant.dashboard', ['tenant' => $slug]);
    }
}
