<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\ActivateKioskDevice;
use App\Modules\Attendance\Actions\IssueKioskDevice;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->center = WorkCenterFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'name' => 'Planta Norte']);
    workIn($this->tenant);
});

/** The token of the link RH hands to a new screen: good for one activation. */
function kioskLinkToken(): string
{
    return resolve(IssueKioskDevice::class)->handle(test()->center->id);
}

/** The secret an activated screen keeps in its cookie. */
function kioskToken(): string
{
    return resolve(ActivateKioskDevice::class)->handle(kioskLinkToken()) ?? throw new LogicException('Not activated.');
}

describe('the panel', function (): void {
    it('lets RH issue a screen for a center, showing its link once', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

        $response = $this->post(route('tenant.attendance.kiosks.store', $this->tenant->slug), ['work_center_id' => $this->center->id]);

        $response->assertRedirect()->assertSessionHas('inertia.flash_data.kioskLink');
        $link = session('inertia.flash_data.kioskLink');
        $token = str($link)->afterLast('/')->toString();
        expect($link)->toBe(route('attendance.device.kiosk.activate', [$this->tenant->slug, $token]))
            ->and(KioskDevice::query()->sole())->work_center_id->toBe($this->center->id)->token_hash->toBe(hash('sha256', $token));
    });

    it('answers 404 for an archived center and asks for a center', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
        $archived = WorkCenterFactory::new()->archived()->createOne(['tenant_id' => $this->tenant->id]);

        $this->post(route('tenant.attendance.kiosks.store', $this->tenant->slug), ['work_center_id' => $archived->id])->assertNotFound();
        $this->post(route('tenant.attendance.kiosks.store', $this->tenant->slug), ['work_center_id' => 'x'])->assertSessionHasErrors('work_center_id');
        expect(KioskDevice::query()->count())->toBe(0);
    });

    it('lets RH revoke a screen of the company, and only of it', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
        kioskToken();
        $kiosk = KioskDevice::query()->sole();
        $other = KioskDevice::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->delete(route('tenant.attendance.kiosks.destroy', [$this->tenant->slug, $kiosk->id]))->assertRedirect();
        $this->delete(route('tenant.attendance.kiosks.destroy', [$this->tenant->slug, $other->id]))->assertNotFound();

        expect($kiosk->fresh()?->revoked_at)->not->toBeNull()
            ->and($other->fresh()?->revoked_at)->toBeNull();
    });

    it('does not let a viewer issue or revoke a screen', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
        kioskToken();
        $kiosk = KioskDevice::query()->sole();

        $this->post(route('tenant.attendance.kiosks.store', $this->tenant->slug), ['work_center_id' => $this->center->id])->assertForbidden();
        $this->delete(route('tenant.attendance.kiosks.destroy', [$this->tenant->slug, $kiosk->id]))->assertForbidden();

        expect(KioskDevice::query()->count())->toBe(1)
            ->and($kiosk->fresh()?->revoked_at)->toBeNull();
    });
});

describe('the screen', function (): void {
    it('keeps its token in an httpOnly cookie of the company\'s device paths and leaves it out of the address', function (): void {
        $token = kioskLinkToken();

        // Tapped in an email or a chat: a navigation from another site.
        $response = $this->withHeaders(['Sec-Fetch-Site' => 'cross-site', 'Referer' => 'https://mail.example.com/'])
            ->get(route('attendance.device.kiosk.activate', [$this->tenant->slug, $token]));

        $response->assertRedirect(route('attendance.device.kiosk.show', $this->tenant->slug));
        $cookie = collect($response->headers->getCookies())->firstOrFail(fn ($cookie): bool => $cookie->getName() === 'attendance_kiosk');
        expect($cookie->isHttpOnly())->toBeTrue()
            ->and($cookie->getPath())->toBe("/a/{$this->tenant->slug}")
            ->and($cookie->getSameSite())->toBe('lax')
            ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addYears(4)->getTimestamp());
    });

    it('opens its link once: the cookie gets a new secret and the link dies', function (): void {
        $link = route('attendance.device.kiosk.activate', [$this->tenant->slug, kioskLinkToken()]);

        $secret = $this->get($link)->assertRedirect()->getCookie('attendance_kiosk')?->getValue();

        expect($secret)->toBeString()
            ->and(KioskDevice::query()->sole()->token_hash)->toBe(hash('sha256', (string) $secret));
        $this->get($link)->assertNotFound()->assertCookieMissing('attendance_kiosk');
        $this->withCookie('attendance_kiosk', (string) $secret)->get(route('attendance.device.kiosk.show', $this->tenant->slug))->assertOk();
        $this->withCookie('attendance_kiosk', str($link)->afterLast('/')->toString())->get(route('attendance.device.kiosk.show', $this->tenant->slug))->assertNotFound();
    });

    it('does not open the link of a revoked screen or of an archived center', function (): void {
        $revoked = kioskLinkToken();
        KioskDevice::query()->update(['revoked_at' => now()]);
        $archived = kioskLinkToken();
        $this->center->update(['archived_at' => now()]);

        $this->get(route('attendance.device.kiosk.activate', [$this->tenant->slug, $revoked]))->assertNotFound();
        $this->get(route('attendance.device.kiosk.activate', [$this->tenant->slug, $archived]))->assertNotFound();
        expect(KioskDevice::query()->whereNotNull('activated_at')->count())->toBe(0);
    });

    it('tells the screen how often its code changes, from config/attendance.php', function (): void {
        $token = kioskToken();
        $show = fn () => $this->withCookie('attendance_kiosk', $token)->get(route('attendance.device.kiosk.show', $this->tenant->slug));

        $show()->assertInertia(fn (AssertableInertia $page) => $page->where('qrSeconds', 30));
        config(['attendance.qr.window_seconds' => 45]);
        $show()->assertInertia(fn (AssertableInertia $page) => $page->where('qrSeconds', 45));
    });

    it('shows the page of the QR to a screen with its token', function (): void {
        $token = kioskToken();

        $this->withCookie('attendance_kiosk', $token)->get(route('attendance.device.kiosk.show', $this->tenant->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/kiosk')
                ->where('company', 'Acme')
                ->where('center', 'Planta Norte')
                ->where('qrUrl', route('attendance.device.kiosk.qr', $this->tenant->slug))
                // The clock on the screen is read on the company's clock, whatever the tablet's zone.
                ->where('timezone', 'America/Mexico_City')
                ->missing('token'));
    });

    it('gives the current code of its center, as the address the phones open', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:10', 'UTC'));
        $token = kioskToken();

        $response = $this->withCredentials()->withCookie('attendance_kiosk', $token)->getJson(route('attendance.device.kiosk.qr', $this->tenant->slug));

        $code = QrToken::issue($this->tenant->id, $this->center->id, CarbonImmutable::now());
        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'token' => $code,
                'url' => url("a/{$this->tenant->slug}/punch").'?q='.$code,
                'qr' => $response->json('qr'),
                'expiresAt' => '2026-11-07T12:00:30+00:00',
                'expiresIn' => 20,
            ]);
        expect($response->json('qr'))->toStartWith('data:image/svg+xml;base64,')
            ->and(KioskDevice::query()->sole()->last_seen_at?->toIso8601String())->toBe('2026-11-07T12:00:10+00:00');
    });

    it('writes when it was last seen at most once a minute', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:00', 'UTC'));
        $token = kioskToken();
        $qr = fn () => $this->withCredentials()->withCookie('attendance_kiosk', $token)->getJson(route('attendance.device.kiosk.qr', $this->tenant->slug))->assertOk();

        $qr();
        $this->travel(59)->seconds();
        $qr();
        expect(KioskDevice::query()->sole()->last_seen_at?->toIso8601String())->toBe('2026-11-07T12:00:00+00:00');

        $this->travel(1)->seconds();
        $qr();
        expect(KioskDevice::query()->sole()->last_seen_at?->toIso8601String())->toBe('2026-11-07T12:01:00+00:00');
    });

    it('answers the same 404 to a screen with no token, an unknown, revoked or foreign one, or an archived center', function (Closure $token): void {
        $cookie = $token();
        $request = fn () => $cookie === null ? $this : $this->withCredentials()->withCookie('attendance_kiosk', $cookie);

        $request()->get(route('attendance.device.kiosk.show', $this->tenant->slug))->assertNotFound();
        $request()->getJson(route('attendance.device.kiosk.qr', $this->tenant->slug))->assertNotFound();
        $this->get(route('attendance.device.kiosk.activate', [$this->tenant->slug, $cookie ?? 'x']))->assertNotFound()->assertCookieMissing('attendance_kiosk');
    })->with([
        'no token' => [fn (): ?string => null],
        'unknown' => [fn (): string => 'no-es-un-token'],
        'revoked' => [function (): string {
            $token = kioskToken();
            KioskDevice::query()->update(['revoked_at' => now()]);

            return $token;
        }],
        'of another company' => [function (): string {
            $other = Tenant::factory()->create();
            workIn($other);
            $token = resolve(ActivateKioskDevice::class)->handle(resolve(IssueKioskDevice::class)->handle(WorkCenterFactory::new()->createOne(['tenant_id' => $other->id])->id));
            workIn(test()->tenant);

            return $token;
        }],
        'archived center' => [function (): string {
            $token = kioskToken();
            test()->center->update(['archived_at' => now()]);

            return $token;
        }],
    ]);

    it('answers 404 under a suspended company', function (): void {
        $token = kioskToken();
        $this->tenant->update(['deactivated_at' => now()]);

        $this->withCredentials()->withCookie('attendance_kiosk', $token)->getJson(route('attendance.device.kiosk.qr', $this->tenant->slug))->assertNotFound();
    });

    it('throttles every device route by address', function (): void {
        foreach (['attendance.device.kiosk.activate', 'attendance.device.kiosk.show', 'attendance.device.kiosk.qr'] as $name) {
            expect(Route::getRoutes()->getByName($name)?->gatherMiddleware())->toContain('throttle:attendance-device');
        }

        $limit = RateLimiter::limiter('attendance-device')?->__invoke(request());
        expect($limit)->maxAttempts->toBe(120)->decaySeconds->toBe(60)->key->toBe('attendance-device:'.request()->ip());
    });
});
