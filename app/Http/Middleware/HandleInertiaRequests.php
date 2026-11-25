<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Impersonation;
use App\Models\User;
use App\Modules\Campaigns\Contracts\OpenCampaigns;
use App\Modules\FirstSteps\Contracts\FirstSteps;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use stdClass;

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
            // Where to write to Humetria; null while none is configured, so no notice carries an empty mail link.
            'support' => ['email' => $this->supportEmail()],
            // The profiles configured for the landing footer: only https links, in a fixed order.
            'social' => $this->socialLinks(),
            'auth' => [
                'user' => $request->user(),
                'isSuperadmin' => $request->user()?->isSuperadmin() ?? false,
                'isStaff' => $request->user()?->isStaff() ?? false,
            ],
            // The superadmin working as auth.user, for the banner that lets them out; null otherwise.
            'impersonator' => $this->impersonatorName(),
            // Seconds before that entry ends by itself, for the banner's countdown; only while someone is impersonating, so a
            // page without a session (and its SSR golden file) does not change.
            ...$this->impersonationLeft(),
            // The company routes override these (Tenancy's ResolveTenant); the kernel only gives the defaults.
            'tenant' => null,
            'tenants' => [],
            // The NOM-035 count and the next deadline in the sidebar. Read when the page renders, after ResolveTenant
            // has shared the company: outside one, nothing is asked.
            // Only whoever sees NOM-035's route is told its evaluations, not even their counts: the Comisión and the handlers
            // of complaints never are (NOM-037, criterio C-25), nor a role of the company's own without that permission.
            'inCourse' => fn (): ?array => $this->seesNom035() ? $this->inCourse() : null,
            // The guided tour of the company's owner: nobody else is asked anything, and outside a company nothing is.
            'firstSteps' => fn (): ?array => $this->firstSteps($request),
            // Every page of the demo company, its reports above all, carries the «DEMO» mark (DemoMark). Read when the page
            // renders: the company routes and the report links set the company before then.
            'demo' => fn (): bool => app(DemoCompanies::class)->isCurrent(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return list<array{network: string, url: string}>
     */
    private function socialLinks(): array
    {
        $links = [];

        foreach (['linkedin', 'facebook', 'instagram'] as $network) {
            $url = config("app.social.{$network}");

            if (is_string($url) && str_starts_with($url, 'https://')) {
                $links[] = ['network' => $network, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * What every page shares, read from nothing that can be down (no database, no session, no user): the error page
     * falls back to it when share() is what failed.
     *
     * @return array<string, mixed>
     */
    public function safeShare(Request $request): array
    {
        return [
            'errors' => new stdClass,
            'name' => config('app.name'),
            'support' => ['email' => $this->supportEmail()],
            'social' => $this->socialLinks(),
            'auth' => ['user' => null, 'isSuperadmin' => false, 'isStaff' => false],
            'impersonator' => null,
            'tenant' => null,
            'tenants' => [],
            'inCourse' => null,
            'firstSteps' => null,
            'demo' => false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function supportEmail(): ?string
    {
        $email = config('app.support_email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstSteps(Request $request): ?array
    {
        $tenant = Inertia::getShared('tenant');
        $user = $request->user();

        if ($user === null || ! is_array($tenant) || ($tenant['role'] ?? null) !== 'owner') {
            return null;
        }

        return app(FirstSteps::class)->forUser($user->id, impersonated: app(Impersonation::class)->isActive())?->toArray();
    }

    private function seesNom035(): bool
    {
        $tenant = Inertia::getShared('tenant');

        return is_array($tenant) && is_array($tenant['permissions'] ?? null) && ($tenant['permissions']['view_nom035_route'] ?? false) === true;
    }

    /**
     * @return array{count: int, daysLeft: int|null, elapsed: int}
     */
    private function inCourse(): array
    {
        $inCourse = app(OpenCampaigns::class)->inCourse();

        return ['count' => $inCourse->count, 'daysLeft' => $inCourse->daysLeft, 'elapsed' => $inCourse->elapsed];
    }

    /**
     * @return array{impersonationLeft?: int}
     */
    private function impersonationLeft(): array
    {
        $left = app(Impersonation::class)->secondsLeft();

        return $left === null ? [] : ['impersonationLeft' => $left];
    }

    private function impersonatorName(): ?string
    {
        $id = app(Impersonation::class)->impersonatorId();

        return $id === null ? null : User::query()->find($id)?->name;
    }
}
