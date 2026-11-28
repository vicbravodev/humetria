<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\CreateAccessLink;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Http\Controllers\ShortLinkController;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(ShortLinkController::class);

/*
 * The button of a WhatsApp template takes its variable only at the end of the address, and the link of a company has its slug in
 * the middle (/me/{slug}/link/{token}): `access/{token}` finds the company by the token and sends the person to that link,
 * without spending it.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'email' => 'ana@acme.mx']);
    [$this->link, $url] = resolve(CreateAccessLink::class)->handle($this->ana->id, LinkKind::Issued, ownerOf($this->tenant)->id);
    $this->token = basename((string) parse_url($url, PHP_URL_PATH));
    // The request comes from nowhere: no company is current until the token names one.
    workIn(Tenant::factory()->create());
});

it('redirects a short link to the company link without using it', function (): void {
    expect(route('worker.link.short', $this->token))->toBe(rtrim(config()->string('app.url'), '/').'/access/'.$this->token);

    $this->get(route('worker.link.short', $this->token))->assertRedirect(route('worker.link.show', ['acme', $this->token]));

    workIn($this->tenant);
    expect($this->link->refresh()->used_at)->toBeNull()
        ->and(WorkerAccessLink::findPending($this->token)?->id)->toBe($this->link->id);
});

it('answers an unknown short link like a bad link', function (Closure $spoil): void {
    $token = $spoil($this);

    $bad = fn (AssertableInertia $page): AssertableInertia => $page->component('worker-access/link')->where('opens', false)->where('firstUse', false);
    $this->get(route('worker.link.show', ['acme', 'not-a-link']))->assertOk()->assertInertia($bad);
    $this->get(route('worker.link.short', $token))->assertOk()->assertInertia($bad);
})->with([
    'unknown' => [fn (): string => 'not-a-link'],
    'expired' => [function ($test): string {
        $test->travel(49)->hours();

        return $test->token;
    }],
    'used' => [function ($test): string {
        WorkerAccessLink::query()->withoutGlobalScope('tenant')->whereKey($test->link->id)->update(['used_at' => now()]);

        return $test->token;
    }],
    'revoked' => [function ($test): string {
        WorkerAccessLink::query()->withoutGlobalScope('tenant')->whereKey($test->link->id)->update(['revoked_at' => now()]);

        return $test->token;
    }],
]);

it('throttles short links', function (): void {
    foreach (range(1, 10) as $_) {
        $this->get(route('worker.link.short', $this->token))->assertRedirect();
    }

    $this->get(route('worker.link.short', $this->token))->assertTooManyRequests();
});
