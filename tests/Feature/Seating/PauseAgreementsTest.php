<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\SavePauseAgreement;
use App\Modules\Seating\Http\Controllers\PauseAgreementController;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SavePauseAgreement::class, PauseAgreementController::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.seating_files'));
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agreementForm(array $overrides = []): array
{
    return ['position_id' => test()->position->id, 'pauses_per_shift' => 3, 'minutes_each' => 10, 'agreed_on' => '2026-02-20', 'how_agreed' => 'Se acordó con las cajeras en la reunión mensual.', ...$overrides];
}

function agreementUrl(?WorkCenter $center = null): string
{
    return route('tenant.seating.pause-agreements.store', [test()->tenant, ($center ?? test()->center)->id]);
}

it('records an agreement per position with how it was reached', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    $this->post(agreementUrl(), agreementForm())->assertRedirect()->assertSessionHasNoErrors();

    $agreement = PauseAgreement::query()->sole();
    expect($agreement->position_id)->toBe($this->position->id)->and($agreement->work_center_id)->toBe($this->center->id)
        ->and([$agreement->pauses_per_shift, $agreement->minutes_each])->toBe([3, 10])
        ->and($agreement->agreed_on->toDateString())->toBe('2026-02-20')
        ->and($agreement->how_agreed)->toBe('Se acordó con las cajeras en la reunión mensual.');
    Log::shouldHaveReceived('info')->with('seating.pause_agreement_saved', ['agreement_id' => $agreement->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id])->once();
});

it('accepts any minutes above zero and sets no minimum, because the law fixes none', function (int $minutes): void {
    $this->actingAs($this->hr);

    $this->post(agreementUrl(), agreementForm(['minutes_each' => $minutes]))->assertSessionHasNoErrors();

    expect(PauseAgreement::query()->sole()->minutes_each)->toBe($minutes);
})->with([1, 2, 5, 15, 60]);

it('rejects what an agreement cannot be', function (array $bad, string $field): void {
    $this->actingAs($this->hr);

    $this->post(agreementUrl(), agreementForm($bad))->assertSessionHasErrors($field);

    expect(PauseAgreement::query()->count())->toBe(0);
})->with([
    'zero minutes' => [['minutes_each' => 0], 'minutes_each'],
    'no pauses' => [['pauses_per_shift' => 0], 'pauses_per_shift'],
    'no position' => [['position_id' => null], 'position_id'],
    'no way of agreeing' => [['how_agreed' => '  '], 'how_agreed'],
    'a future date' => [['agreed_on' => '2026-03-02'], 'agreed_on'],
    'no date' => [['agreed_on' => null], 'agreed_on'],
]);

it('keeps the earlier agreements: a new one for the same position is a new row and the latest counts', function (): void {
    $this->actingAs($this->hr);
    $this->post(agreementUrl(), agreementForm(['agreed_on' => '2026-01-10', 'minutes_each' => 5]));
    $this->post(agreementUrl(), agreementForm(['agreed_on' => '2026-02-20', 'minutes_each' => 10]));

    expect(PauseAgreement::query()->count())->toBe(2);
    $this->get(route('tenant.seating.plan', [$this->tenant, $this->center->id]))->assertInertia(fn (Assert $page) => $page->where('agreements.total', 2)->where('agreements.data.0.minutesEach', 10)->where('agreements.data.0.agreedOn', '2026-02-20'));
});

it('keeps the evidence of the agreement, a PDF or an image, and serves it only to who may see the plan', function (): void {
    $this->actingAs($this->hr);
    $this->post(agreementUrl(), agreementForm(['evidence' => UploadedFile::fake()->createWithContent('acta.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF")]))->assertSessionHasNoErrors();
    $agreement = PauseAgreement::query()->sole();
    Storage::disk(config()->string('filesystems.seating_files'))->assertExists((string) $agreement->evidence_path);
    $url = route('tenant.seating.pause-agreements.evidence', [$this->tenant, $agreement->id]);

    $response = $this->get($url)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url)->assertForbidden();
});

it('lets only manage-seating record agreements, and the committee only read them', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $this->post(agreementUrl(), agreementForm())->assertForbidden();

    expect(PauseAgreement::query()->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('refuses a position of another company, of another center or a made-up one, and a center that is not ours', function (): void {
    $foreign = StandingPosition::factory()->create();
    $elsewhere = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);
    $this->actingAs($this->hr);

    foreach ([$foreign->id, $elsewhere->id, (string) Illuminate\Support\Str::uuid()] as $position) {
        $this->post(agreementUrl(), agreementForm(['position_id' => $position]))->assertSessionHasErrors('position_id');
    }
    $this->post(agreementUrl(WorkCenter::factory()->create()), agreementForm())->assertNotFound();
    expect(PauseAgreement::query()->count())->toBe(0);
});

it('has no destroy or update route for agreements', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => str_contains((string) $route->getName(), 'seating.pause-agreements') && array_intersect(['DELETE', 'PUT', 'PATCH'], $route->methods()) !== []);

    expect($routes)->toHaveCount(0);
});

it('refuses in the database zero pauses or zero minutes', function (): void {
    PauseAgreement::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $attempts = [
        'pause_agreements_pauses_positive' => fn () => DB::table('pause_agreements')->update(['pauses_per_shift' => 0]),
        'pause_agreements_minutes_positive' => fn () => DB::table('pause_agreements')->update(['minutes_each' => 0]),
    ];

    foreach ($attempts as $constraint => $attempt) {
        expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class, $constraint);
    }
});
