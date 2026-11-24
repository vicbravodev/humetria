<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Http\Controllers\InstallableAppController;
use Carbon\CarbonImmutable;

mutates(InstallableAppController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']);
    workIn($this->tenant);
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
});

describe('the manifest', function (): void {
    it('is the company\'s own: Marcar opens from the icon, inside the person\'s pages', function (): void {
        $response = $this->get(route('worker.app.manifest', 'acme'))->assertOk();

        expect($response->headers->get('Content-Type'))->toStartWith('application/manifest+json')
            ->and($response->json('id'))->toBe('/me/acme/punch')
            ->and($response->json('scope'))->toBe('/me/acme')
            ->and($response->json('start_url'))->toBe('/me/acme/punch')
            ->and($response->json('name'))->toBe('Acme Industrial')
            ->and($response->json('short_name'))->toBe('Acme Industr')
            ->and($response->json('lang'))->toBe('es-MX')
            ->and($response->json('dir'))->toBe('ltr')
            ->and($response->json('display'))->toBe('standalone')
            ->and($response->json('background_color'))->toBe('#FBFDFC')
            ->and($response->json('theme_color'))->toBe('#FBFDFC');
    });

    it('opens the person\'s start when the company has no registro de jornada, where Marcar would answer 404', function (): void {
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));

        $response = $this->get(route('worker.app.manifest', 'acme'))->assertOk();

        expect($response->json('start_url'))->toBe('/me/acme')
            ->and($response->json('id'))->toBe('/me/acme/punch');
    });

    it('lists four icons and every file exists', function (): void {
        $icons = $this->get(route('worker.app.manifest', 'acme'))->json('icons');

        expect($icons)->toHaveCount(4)
            ->and(collect($icons)->pluck('purpose')->all())->toBe(['any', 'any', 'any', 'maskable']);

        foreach ($icons as $icon) {
            expect(public_path(ltrim((string) $icon['src'], '/')))->toBeFile();
        }
    });

    it('tells two companies apart by name and by address', function (): void {
        $other = Tenant::factory()->create(['trade_name' => 'Boreal', 'slug' => 'boreal']);
        resolve(CompanyModules::class)->syncPlan($other->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));

        $mine = $this->get(route('worker.app.manifest', 'acme'))->json();
        $theirs = $this->get(route('worker.app.manifest', 'boreal'))->json();

        expect($theirs['name'])->toBe('Boreal')
            ->and($theirs['scope'])->toBe('/me/boreal')
            ->and($theirs['start_url'])->toBe('/me/boreal/punch')
            ->and($mine['name'])->toBe('Acme Industrial')
            ->and($mine['scope'])->toBe('/me/acme');
    });

    it('carries nothing of anyone: no cookie in, no cookie out, the same for a person with a session', function (): void {
        $visitor = $this->get(route('worker.app.manifest', 'acme'))->assertOk()->assertHeaderMissing('Set-Cookie');
        $withSession = $this->withCookie(config()->string('session.cookie'), 'a-session-id')->get(route('worker.app.manifest', 'acme'))
            ->assertOk()->assertHeaderMissing('Set-Cookie');

        expect($withSession->getContent())->toBe($visitor->getContent())
            ->and($visitor->getContent())->not->toContain('Ana');
    });

    it('answers the same 404 for a company that does not exist and for a blocked one', function (): void {
        Tenant::factory()->create(['slug' => 'blocked', ...suspension()]);

        $this->get(route('worker.app.manifest', 'nadie'))->assertNotFound();
        $this->get(route('worker.app.manifest', 'blocked'))->assertNotFound();
    });
});
