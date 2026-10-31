<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Impersonation;
use App\Models\User;
use App\Modules\Campaigns\Contracts\OpenCampaigns;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'isSuperadmin' => $request->user()?->isSuperadmin() ?? false,
            ],
            // The superadmin working as auth.user, for the banner that lets them out; null otherwise.
            'impersonator' => $this->impersonatorName(),
            // The company routes override these (Tenancy's ResolveTenant); the kernel only gives the defaults.
            'tenant' => null,
            'tenants' => [],
            // The NOM-035 count and the next deadline in the sidebar. Read when the page renders, after ResolveTenant
            // has shared the company: outside one, nothing is asked.
            'inCourse' => fn (): ?array => Inertia::getShared('tenant') === null ? null : $this->inCourse(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{count: int, daysLeft: int|null, elapsed: int}
     */
    private function inCourse(): array
    {
        $inCourse = app(OpenCampaigns::class)->inCourse();

        return ['count' => $inCourse->count, 'daysLeft' => $inCourse->daysLeft, 'elapsed' => $inCourse->elapsed];
    }

    private function impersonatorName(): ?string
    {
        $id = app(Impersonation::class)->impersonatorId();

        return $id === null ? null : User::query()->find($id)?->name;
    }
}
