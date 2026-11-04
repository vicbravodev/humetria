<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\RecordDissemination;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RecordDissemination::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    workIn($this->tenant);
});

it('records a poster, a bulletin, a leaflet or a meeting with the day and the incisos it covers', function (string $medium): void {
    $record = resolve(RecordDissemination::class)->handle($this->plant->id, $medium, ['a', 'c', 'a'], '2026-11-18', 'Cartel en el comedor', $this->hr->id);

    expect($record->medium->value)->toBe($medium)
        ->and($record->covered_items)->toBe(['a', 'c'])
        ->and($record->done_on->toDateString())->toBe('2026-11-18')
        ->and($record->note)->toBe('Cartel en el comedor')
        ->and($record->recorded_by)->toBe($this->hr->id)
        ->and($record->work_center_id)->toBe($this->plant->id);
})->with(['poster', 'bulletin', 'brochure', 'meeting', 'other']);

it('accepts today and refuses tomorrow', function (): void {
    expect(resolve(RecordDissemination::class)->handle($this->plant->id, 'poster', ['a'], '2026-11-20', '', $this->hr->id)->done_on->toDateString())->toBe('2026-11-20');

    expect(fn () => resolve(RecordDissemination::class)->handle($this->plant->id, 'poster', ['a'], '2026-11-21', '', $this->hr->id))->toThrow(ValidationException::class);
});

it('refuses what the norm does not list: no incisos, an inciso of another numeral, an unknown medium, a center that is not in use', function (Closure $attempt, string $field): void {
    try {
        $attempt($this);
        $this->fail('The record should have been refused.');
    } catch (ValidationException $failure) {
        expect(array_keys($failure->errors()))->toBe([$field]);
    }

    expect(DisseminationRecord::query()->count())->toBe(0);
})->with([
    'no incisos' => [fn ($test) => resolve(RecordDissemination::class)->handle($test->plant->id, 'poster', [], '2026-11-18', '', $test->hr->id), 'covered_items'],
    'an inciso g' => [fn ($test) => resolve(RecordDissemination::class)->handle($test->plant->id, 'poster', ['g'], '2026-11-18', '', $test->hr->id), 'covered_items'],
    'an unknown medium' => [fn ($test) => resolve(RecordDissemination::class)->handle($test->plant->id, 'tv', ['a'], '2026-11-18', '', $test->hr->id), 'medium'],
    'a center of another company' => [fn ($test) => resolve(RecordDissemination::class)->handle(WorkCenter::factory()->for(Tenant::factory()->create())->create()->id, 'poster', ['a'], '2026-11-18', '', $test->hr->id), 'work_center_id'],
    'an archived center' => [fn ($test) => resolve(RecordDissemination::class)->handle(WorkCenter::factory()->for($test->tenant)->archived()->create()->id, 'poster', ['a'], '2026-11-18', '', $test->hr->id), 'work_center_id'],
]);

it('logs the record without its note', function (): void {
    Log::spy();

    $record = resolve(RecordDissemination::class)->handle($this->plant->id, 'bulletin', ['b', 'a'], '2026-11-18', 'Nota larga y secreta', $this->hr->id);

    Log::shouldHaveReceived('info')->with('disclosure.dissemination_recorded', ['record_id' => $record->id, 'work_center_id' => $this->plant->id, 'medium' => 'bulletin', 'covered_items' => ['a', 'b']])->once();
});

it('never changes or deletes a record: it is evidence', function (): void {
    $record = DisseminationRecord::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $try = fn (Closure $statement) => expect(fn () => DB::transaction($statement))->toThrow(QueryException::class, 'evidence');

    $try(fn () => DB::table('disclosure_dissemination_records')->where('id', $record->id)->update(['note' => 'cambiada']));
    $try(fn () => DB::table('disclosure_dissemination_records')->where('id', $record->id)->delete());
});

it('refuses at the database what the checks refuse', function (array $attributes, string $constraint): void {
    $row = [...DisseminationRecord::factory()->raw(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]), ...$attributes];
    $row = array_map(fn ($value) => is_array($value) ? json_encode($value) : $value, $row);

    expect(fn () => DB::transaction(fn () => DB::table('disclosure_dissemination_records')->insert([...$row, 'id' => (string) Illuminate\Support\Str::uuid(), 'created_at' => now(), 'updated_at' => now()])))->toThrow(QueryException::class, $constraint);
})->with([
    'an unknown medium' => [['medium' => 'tv'], 'disclosure_dissemination_records_medium_known'],
    'no incisos' => [['covered_items' => []], 'disclosure_dissemination_records_items_valid'],
    'an inciso outside a to f' => [['covered_items' => ['a', 'g']], 'disclosure_dissemination_records_items_valid'],
    'incisos that are not a list' => [['covered_items' => ['a' => 1]], 'disclosure_dissemination_records_items_valid'],
]);

it('scopes the records to the current company', function (): void {
    $other = Tenant::factory()->create();
    DisseminationRecord::factory()->create(['tenant_id' => $other->id]);
    $own = DisseminationRecord::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    expect(DisseminationRecord::query()->pluck('id')->all())->toBe([$own->id]);
});

it('records from the form and lists the records on the page, newest first, a page at a time', function (): void {
    $post = fn (array $body) => $this->actingAs($this->hr)->post(route('tenant.disclosure.dissemination.records.store', $this->tenant), $body);

    $post(['work_center_id' => $this->plant->id, 'medium' => 'poster', 'covered_items' => ['a', 'b'], 'done_on' => '2026-11-18', 'note' => 'Comedor'])
        ->assertRedirect(route('tenant.disclosure.dissemination', $this->tenant))->assertSessionHasNoErrors();
    foreach (range(1, 6) as $day) {
        resolve(RecordDissemination::class)->handle($this->plant->id, 'meeting', ['d'], sprintf('2026-11-%02d', $day), '', $this->hr->id);
    }

    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.total', 7)
            ->where('records.per_page', 5)
            ->where('records.data.0', fn ($row) => $row['workCenter'] === 'Planta Norte' && $row['mediumLabel'] === 'Cartel' && $row['doneOn'] === '18 nov 2026' && $row['items'] === ['a', 'b'] && $row['note'] === 'Comedor')
            ->has('records.data', 5));
});

it('answers what is wrong with a record on its field, and lets only whoever manages record', function (array $body, string $field): void {
    $this->actingAs($this->hr)->post(route('tenant.disclosure.dissemination.records.store', $this->tenant), [...['work_center_id' => $this->plant->id, 'medium' => 'poster', 'covered_items' => ['a'], 'done_on' => '2026-11-18'], ...$body])
        ->assertSessionHasErrors($field);
})->with([
    'no center' => [['work_center_id' => null], 'work_center_id'],
    'a center that is not a uuid' => [['work_center_id' => 'x'], 'work_center_id'],
    'an unknown medium' => [['medium' => 'tv'], 'medium'],
    'no incisos' => [['covered_items' => []], 'covered_items'],
    'an inciso g' => [['covered_items' => ['g']], 'covered_items.0'],
    'no day' => [['done_on' => null], 'done_on'],
    'tomorrow' => [['done_on' => '2026-11-21'], 'done_on'],
    'a long note' => [['note' => str_repeat('a', 501)], 'note'],
]);

it('lets only whoever manages record', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))->post(route('tenant.disclosure.dissemination.records.store', $this->tenant), ['work_center_id' => $this->plant->id, 'medium' => 'poster', 'covered_items' => ['a'], 'done_on' => '2026-11-18'])->assertForbidden();

    expect(DisseminationRecord::query()->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);
