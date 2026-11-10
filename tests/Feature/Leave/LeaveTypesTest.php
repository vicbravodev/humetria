<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\SaveLeaveType;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(SaveLeaveType::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
});

/**
 * @return array<string, list<string>>
 */
function typeErrors(Closure $save): array
{
    try {
        $save();
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

it('seeds exactly the five leave types of the law, once', function (): void {
    resolve(LeaveDefaults::class)->ensure();
    resolve(LeaveDefaults::class)->ensure();

    expect(LeaveType::query()->orderBy('key')->get()->map(fn (LeaveType $type): array => [$type->key, $type->name, $type->paid, $type->archived_at])->all())->toBe([
        ['adoption', 'Adopción', true, null],
        ['maternity', 'Maternidad', true, null],
        ['paternity', 'Paternidad', true, null],
        ['special_paid', 'Permiso especial con goce', true, null],
        ['special_unpaid', 'Permiso especial sin goce', false, null],
    ]);
});

it('seeds each company its own types', function (): void {
    resolve(LeaveDefaults::class)->ensure();
    workIn($other = Tenant::factory()->create());
    resolve(LeaveDefaults::class)->ensure();

    expect(LeaveType::query()->count())->toBe(5)
        ->and(LeaveType::query()->withoutGlobalScopes()->whereIn('tenant_id', [$this->tenant->id, $other->id])->count())->toBe(10);
});

it('never renames, changes or archives a seeded type', function (): void {
    resolve(LeaveDefaults::class)->ensure();
    $paternity = LeaveType::query()->where('key', 'paternity')->sole();

    expect(typeErrors(fn () => resolve(SaveLeaveType::class)->handle('Permiso de papá', true, $paternity->id)))->toBe(['name' => ['Este permiso viene de la ley: no se puede cambiar.']])
        ->and(typeErrors(fn () => resolve(SaveLeaveType::class)->handle('Paternidad', false, $paternity->id)))->toHaveKey('name')
        ->and(typeErrors(fn () => resolve(SaveLeaveType::class)->archive($paternity->id)))->toBe(['type_id' => ['Este permiso viene de la ley: no se puede archivar.']])
        ->and($paternity->refresh()->only(['name', 'paid', 'archived_at']))->toBe(['name' => 'Paternidad', 'paid' => true, 'archived_at' => null]);
});

it('lets the company add a type of its own, change it and archive it', function (): void {
    $this->travelTo('2026-11-10 18:00:00');
    $type = resolve(SaveLeaveType::class)->handle('  Mudanza ', false);

    expect($type->refresh()->only(['tenant_id', 'key', 'name', 'paid', 'archived_at']))->toBe(['tenant_id' => $this->tenant->id, 'key' => null, 'name' => 'Mudanza', 'paid' => false, 'archived_at' => null]);

    $same = resolve(SaveLeaveType::class)->handle('Mudanza de casa', true, $type->id);
    expect($same->id)->toBe($type->id)
        ->and($type->refresh()->only(['name', 'paid']))->toBe(['name' => 'Mudanza de casa', 'paid' => true])
        ->and(LeaveType::query()->count())->toBe(1);

    resolve(SaveLeaveType::class)->archive($type->id);
    $this->travelTo('2026-11-11 18:00:00');
    resolve(SaveLeaveType::class)->archive($type->id);
    expect($type->refresh()->archived_at?->toDateTimeString())->toBe('2026-11-10 18:00:00');
});

it('refuses a type without a name or with the name of another one', function (): void {
    resolve(LeaveDefaults::class)->ensure();
    $moving = resolve(SaveLeaveType::class)->handle('Mudanza', false);

    expect(typeErrors(fn () => resolve(SaveLeaveType::class)->handle('  ', true)))->toBe(['name' => ['Escribe el nombre del permiso.']])
        ->and(typeErrors(fn () => resolve(SaveLeaveType::class)->handle('Maternidad', true)))->toBe(['name' => ['Ya hay un permiso con ese nombre.']])
        ->and(typeErrors(fn () => resolve(SaveLeaveType::class)->handle(str_repeat('a', 81), true)))->toBe(['name' => ['El nombre no puede pasar de 80 caracteres.']])
        ->and(resolve(SaveLeaveType::class)->handle(str_repeat('a', 80), true)->exists)->toBeTrue()
        // Saving it again with its own name is not a duplicate.
        ->and(resolve(SaveLeaveType::class)->handle('Mudanza', true, $moving->id)->paid)->toBeTrue()
        ->and(LeaveType::query()->count())->toBe(7);
});

it('lets two companies use the same name', function (): void {
    resolve(SaveLeaveType::class)->handle('Mudanza', false);
    workIn(Tenant::factory()->create());

    expect(resolve(SaveLeaveType::class)->handle('Mudanza', false)->name)->toBe('Mudanza');
});

it('rejects in the database a repeated name or key in a company, and a key it does not know', function (): void {
    LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mudanza']);
    LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'paternity']);

    // Each in a savepoint: a refused insert must not end the test's transaction.
    expect(fn () => DB::transaction(fn () => LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mudanza'])))->toThrow(QueryException::class, 'leave_types_tenant_id_name_unique')
        ->and(fn () => DB::transaction(fn () => LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'paternity'])))->toThrow(QueryException::class, 'leave_types_tenant_id_key_unique')
        ->and(fn () => DB::transaction(fn () => LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'mourning'])))->toThrow(QueryException::class, 'leave_types_key_known')
        ->and(fn () => DB::transaction(fn () => LeaveType::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'key' => 'maternity'])))->toThrow(QueryException::class, 'leave_types_seeded_stay');
});

it('logs what it saved and archived by id, never by name', function (): void {
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    $type = resolve(SaveLeaveType::class)->handle('Mudanza', false);
    resolve(SaveLeaveType::class)->archive($type->id);
    resolve(SaveLeaveType::class)->archive($type->id);

    expect($lines)->toBe(['leave.type_saved {"type_id":"'.$type->id.'"}', 'leave.type_archived {"type_id":"'.$type->id.'"}']);
});

it('does not read or change the types of another company', function (): void {
    $ofOther = LeaveType::factory()->create(['name' => 'Mudanza']);

    expect(LeaveType::query()->count())->toBe(0)
        ->and(LeaveType::query()->find($ofOther->id))->toBeNull()
        ->and(LeaveType::query()->whereKey($ofOther->id)->update(['name' => 'Tomada']))->toBe(0)
        ->and(fn () => resolve(SaveLeaveType::class)->handle('Tomada', true, $ofOther->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => resolve(SaveLeaveType::class)->archive($ofOther->id))->toThrow(ModelNotFoundException::class)
        ->and($ofOther->refresh()->only(['name', 'archived_at']))->toBe(['name' => 'Mudanza', 'archived_at' => null]);
});
