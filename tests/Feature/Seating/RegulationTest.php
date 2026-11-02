<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\AdvanceRegulation;
use App\Modules\Seating\Actions\DraftRegulation;
use App\Modules\Seating\Http\Controllers\RegulationController;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\RegulationNotice;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DraftRegulation::class, AdvanceRegulation::class, RegulationController::class, RegulationNotice::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.seating_files'));
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
});

function regulationUrl(string $name = 'show'): string
{
    return route("tenant.seating.regulation.{$name}", [test()->tenant, test()->center->id]);
}

function signedPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('rit.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
}

function advance(string $to, array $data = []): Illuminate\Testing\TestResponse
{
    return test()->post(regulationUrl('advance'), ['to' => $to, ...$data]);
}

it('drafts the regulation from the data of the center, once', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    $this->post(regulationUrl('store'))->assertRedirect(regulationUrl())->assertSessionHasNoErrors();
    $this->post(regulationUrl('store'))->assertSessionHasNoErrors();

    $regulation = InternalRegulation::query()->sole();
    expect($regulation->status->value)->toBe('draft')->and($regulation->parts['risk_factors'])->toContain('Cajera')->and($regulation->parts['usage_rule'])->toContain('asiento');
    Log::shouldHaveReceived('info')->with('seating.regulation_drafted', ['regulation_id' => $regulation->id, 'work_center_id' => $this->center->id])->once();
});

it('carries the notice that it is not legal advice, in the page and unremovable', function (): void {
    $this->actingAs($this->hr)->get(regulationUrl())->assertInertia(fn (Assert $page) => $page->component('seating/regulation')->where('regulation', null)
        ->where('notice', fn (string $notice) => str_contains($notice, 'no es asesoría legal') && str_contains($notice, 'abogado') && str_contains($notice, 'comisión mixta')));
    $this->post(regulationUrl('store'));
    $this->get(regulationUrl())->assertInertia(fn (Assert $page) => $page->where('notice', RegulationNotice::TEXT)->where('regulation.status', 'draft'));

    expect(RegulationNotice::TEXT)->toContain('no es asesoría legal');
});

it('edits the parts of a draft, trimmed, and not those of a signed regulation', function (): void {
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));

    $this->put(regulationUrl('update'), ['risk_factors' => ' Factores propios ', 'pauses' => 'Pausas propias', 'seat_location' => 'Lugares propios', 'usage_rule' => 'Norma propia'])->assertRedirect()->assertSessionHasNoErrors();

    expect(InternalRegulation::query()->sole()->parts)->toEqual(['risk_factors' => 'Factores propios', 'pauses' => 'Pausas propias', 'seat_location' => 'Lugares propios', 'usage_rule' => 'Norma propia']);

    advance('signed', ['signed_on' => '2026-02-20', 'file' => signedPdf()])->assertSessionHasNoErrors();
    $this->put(regulationUrl('update'), ['risk_factors' => 'Otro', 'pauses' => 'x', 'seat_location' => 'x', 'usage_rule' => 'x'])->assertSessionHasErrors('status');
    expect(InternalRegulation::query()->sole()->parts['risk_factors'])->toBe('Factores propios');
});

it('goes draft, signed, deposited and distributed, in that order, with its dates, folio and file', function (): void {
    Log::spy();
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));

    advance('signed', ['signed_on' => '2026-02-20', 'file' => signedPdf()])->assertRedirect()->assertSessionHasNoErrors();
    advance('deposited', ['deposited_on' => '2026-02-25', 'deposit_folio' => 'CFCRL-2026-0042'])->assertSessionHasNoErrors();
    advance('distributed', ['distributed_on' => '2026-03-01'])->assertSessionHasNoErrors();

    $regulation = InternalRegulation::query()->sole();
    expect($regulation->status->value)->toBe('distributed')->and($regulation->signed_on?->toDateString())->toBe('2026-02-20')->and($regulation->deposited_on?->toDateString())->toBe('2026-02-25')
        ->and($regulation->deposit_folio)->toBe('CFCRL-2026-0042')->and($regulation->distributed_on?->toDateString())->toBe('2026-03-01');
    Storage::disk(config()->string('filesystems.seating_files'))->assertExists((string) $regulation->file_path);
    expect(AuditEntry::query()->where('event', 'seating.regulation_status_changed')->orderBy('created_at')->get()->map(fn ($entry) => $entry->metadata['to'])->all())->toBe(['signed', 'deposited', 'distributed']);
    Log::shouldHaveReceived('info')->with('seating.regulation_status_changed', ['regulation_id' => $regulation->id, 'work_center_id' => $this->center->id, 'from' => 'draft', 'to' => 'signed'])->once();
});

it('refuses to deposit before it is signed and to distribute before it is deposited, or to skip back', function (): void {
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));

    advance('deposited', ['deposited_on' => '2026-02-25', 'deposit_folio' => 'F-1'])->assertSessionHasErrors('to');
    advance('distributed', ['distributed_on' => '2026-03-01'])->assertSessionHasErrors('to');
    expect(InternalRegulation::query()->sole()->status->value)->toBe('draft');

    advance('signed', ['signed_on' => '2026-02-20', 'file' => signedPdf()]);
    advance('distributed', ['distributed_on' => '2026-03-01'])->assertSessionHasErrors('to');
    advance('signed', ['signed_on' => '2026-02-21', 'file' => signedPdf()])->assertSessionHasErrors('to');
    expect(InternalRegulation::query()->sole()->status->value)->toBe('signed');
});

it('does not move on without what the step needs, or with dates out of order', function (array $first, string $to, array $data, string $field): void {
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));
    if ($first !== []) {
        advance('signed', ['signed_on' => '2026-02-20', 'file' => signedPdf()]);
    }
    if ($to === 'distributed') {
        advance('deposited', ['deposited_on' => '2026-02-25', 'deposit_folio' => 'F-1']);
    }

    advance($to, $data)->assertSessionHasErrors($field);
})->with([
    'signing without a date' => [[], 'signed', ['file' => 'PDF'], 'signed_on'],
    'signing without the file' => [[], 'signed', ['signed_on' => '2026-02-20'], 'file'],
    'signing in the future' => [[], 'signed', ['signed_on' => '2026-03-02', 'file' => 'PDF'], 'signed_on'],
    'depositing without folio' => [['x'], 'deposited', ['deposited_on' => '2026-02-25'], 'deposit_folio'],
    'depositing before it was signed' => [['x'], 'deposited', ['deposited_on' => '2026-02-19', 'deposit_folio' => 'F-1'], 'deposited_on'],
    'distributing before it was deposited' => [['x'], 'distributed', ['distributed_on' => '2026-02-24'], 'distributed_on'],
]);

it('does not mark complete a regulation with a part missing', function (string $part): void {
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));
    $parts = ['risk_factors' => 'a', 'pauses' => 'b', 'seat_location' => 'c', 'usage_rule' => 'd'];
    $this->put(regulationUrl('update'), [...$parts, $part => '  '])->assertSessionHasErrors($part);

    InternalRegulation::query()->update(['parts' => json_encode([...$parts, $part => ''])]);
    advance('signed', ['signed_on' => '2026-02-20', 'file' => 'PDF'])->assertSessionHasErrors();
    advance('signed', ['signed_on' => '2026-02-20', 'file' => signedPdf()])->assertSessionHasErrors('parts');

    expect(InternalRegulation::query()->sole()->status->value)->toBe('draft');
})->with(['risk_factors', 'pauses', 'seat_location', 'usage_rule']);

it('never deposits or sends anything to an authority', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));

    advance('signed', ['signed_on' => '2026-02-20', 'file' => signedPdf()]);
    advance('deposited', ['deposited_on' => '2026-02-25', 'deposit_folio' => 'F-1']);

    Http::assertNothingSent();
});

it('takes only a PDF for the signed regulation, by content', function (): void {
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));

    advance('signed', ['signed_on' => '2026-02-20', 'file' => UploadedFile::fake()->createWithContent('rit.pdf', 'texto plano')])->assertSessionHasErrors('file');

    expect(InternalRegulation::query()->sole()->file_path)->toBeNull();
});

it('serves the signed file only to who may see the center, never cached', function (): void {
    $this->actingAs($this->hr);
    $this->post(regulationUrl('store'));
    advance('signed', ['signed_on' => '2026-02-20', 'file' => signedPdf()]);

    $response = $this->actingAs($this->cora)->get(regulationUrl('file'))->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(regulationUrl('file'))->assertForbidden();
});

it('lets the committee read the regulation but not change it, and hides it from the viewer', function (): void {
    $this->actingAs($this->hr)->post(regulationUrl('store'));

    $this->actingAs($this->cora)->get(regulationUrl())->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    foreach ([['post', 'store', []], ['put', 'update', ['risk_factors' => 'x', 'pauses' => 'x', 'seat_location' => 'x', 'usage_rule' => 'x']], ['post', 'advance', ['to' => 'signed']]] as [$method, $route, $data]) {
        $this->{$method}(regulationUrl($route), $data)->assertForbidden();
    }
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(regulationUrl())->assertForbidden();
});

it('keeps one regulation per center and has no destroy route', function (): void {
    $this->actingAs($this->hr)->post(regulationUrl('store'));
    $deletes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => in_array('DELETE', $route->methods(), true) && str_contains((string) $route->getName(), 'seating.regulation'));

    expect($deletes)->toHaveCount(0)
        ->and(fn () => DB::transaction(fn () => InternalRegulation::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])))->toThrow(QueryException::class, 'internal_regulations_work_center_unique');
});

it('refuses in the database a status without the data it needs', function (): void {
    InternalRegulation::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $attempts = [
        'internal_regulations_status_known' => fn () => DB::table('internal_regulations')->update(['status' => 'lost', 'signed_on' => '2026-01-01', 'file_path' => 'x.pdf', 'deposited_on' => '2026-01-02', 'deposit_folio' => 'F', 'distributed_on' => '2026-01-03']),
        'internal_regulations_status_has_its_data' => fn () => DB::table('internal_regulations')->update(['status' => 'deposited']),
    ];

    foreach ($attempts as $constraint => $attempt) {
        expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class, $constraint);
    }
});

it('does not touch the regulation of another company', function (): void {
    $foreign = WorkCenter::factory()->create();
    $this->actingAs($this->hr);

    $this->get(route('tenant.seating.regulation.show', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->post(route('tenant.seating.regulation.store', [$this->tenant, $foreign->id]))->assertNotFound();
});
