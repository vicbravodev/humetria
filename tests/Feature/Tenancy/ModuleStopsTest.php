<?php

declare(strict_types=1);

use App\Modules\Attendance\Http\Middleware\ResolveDeviceTenant;
use App\Modules\Seating\Actions\SendSeatingDigests;
use App\Modules\Seating\Notifications\SeatingDigest;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\SeatReportFactory;
use Database\Factories\Seating\StandingPositionFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

mutates(CompanySummary::class);

beforeEach(function (): void {
    Route::middleware(['web', ResolveDeviceTenant::class])->get('a/{tenant:slug}/probe', fn (): string => 'ok');
});

it('says a company is stopped for a module when it is blocked or does not have it', function (): void {
    $open = new CompanySummary('t', 'Acme', 'acme', false);
    $limited = new CompanySummary('t', 'Acme', 'acme', false, modules: ['seating']);
    $blocked = new CompanySummary('t', 'Acme', 'acme', true, modules: ['seating']);

    expect($open->allows('nom037'))->toBeTrue()->and($open->stopped('nom037'))->toBeFalse()
        ->and($limited->allows('seating'))->toBeTrue()->and($limited->stopped('seating'))->toBeFalse()
        ->and($limited->allows('nom037'))->toBeFalse()->and($limited->stopped('nom037'))->toBeTrue()
        ->and($blocked->stopped('seating'))->toBeTrue();
});

it('hands out the modules of the company when it is entered, and nothing restricts one that has no plan', function (): void {
    $legacy = Tenant::factory()->create();
    $planned = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($planned->id, ['nom035', 'seating'], CarbonImmutable::now(config('app.display_timezone')));

    expect(resolve(Companies::class)->enter($legacy->id)->modules)->toBeNull()
        ->and(resolve(Companies::class)->enter($planned->id)->modules)->toBe(['nom035', 'seating'])
        ->and(resolve(Companies::class)->current()->stopped('attendance'))->toBeTrue();
});

it('sends the digest of a module only to the companies that have it', function (): void {
    Notification::fake();
    $this->travelTo('2026-03-01 14:00:00');
    $with = Tenant::factory()->create();
    $without = Tenant::factory()->create();
    $ownerWith = ownerOf($with);
    $ownerWithout = ownerOf($without);

    foreach ([$with, $without] as $tenant) {
        $center = WorkCenter::factory()->for($tenant)->create();
        SeatReportFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'created_at' => now()->subDays(20)]);
        StandingPositionFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    }

    resolve(CompanyModules::class)->syncPlan($without->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));
    workIn($with);

    resolve(SendSeatingDigests::class)->handle();

    Notification::assertSentTo(new AnonymousNotifiable, SeatingDigest::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $ownerWith->email);
    Notification::assertNotSentTo(new AnonymousNotifiable, SeatingDigest::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $ownerWithout->email);
});

it('stops the time-clock devices of a company without the attendance module with the same 404 as an unknown company', function (): void {
    $without = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($without->id, ['seating'], CarbonImmutable::now(config('app.display_timezone')));
    $with = Tenant::factory()->create();

    $this->get("a/{$without->slug}/probe")->assertNotFound();
    $this->get("a/{$with->slug}/probe")->assertOk();
});

it('does not leave the id of a company without the attendance module in the context of the request that gets the 404', function (): void {
    $without = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($without->id, ['seating'], CarbonImmutable::now(config('app.display_timezone')));

    $this->get("a/{$without->slug}/probe")->assertNotFound();

    expect(Illuminate\Support\Facades\Context::get('tenant_id'))->toBeNull();
});
