<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Leave\Actions\AdjustBalance;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Support\Balances;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(AdjustBalance::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->user = User::factory()->create();
    // 12 statutory and 3 extra, with 5 statutory taken: 7 and 3 left.
    $this->period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'statutory_days' => 12, 'extra_days' => 3]);
    LeaveEntry::factory()->of($this->period)->taken(5)->create();
});

/**
 * @return array<string, list<string>>
 */
function adjustmentErrors(Closure $adjust): array
{
    try {
        $adjust();
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

/**
 * @return list<int> statutory and extra days left of the period
 */
function leftOf(LeaveEntitlement $period): array
{
    [$balance] = Balances::of([$period->employee_id])[$period->employee_id];

    return [$balance->statutoryLeft, $balance->extraLeft];
}

it('adds an entry to the ledger with who adjusted and why', function (): void {
    $entry = resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, -3, 'Días tomados antes de usar el sistema', $this->user->id);

    expect($entry->refresh()->only(['tenant_id', 'employee_id', 'leave_entitlement_id', 'kind', 'tranche', 'days', 'recorded_by', 'authorized_by', 'leave_request_id', 'note']))->toBe([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->period->employee_id, 'leave_entitlement_id' => $this->period->id, 'kind' => EntryKind::Adjustment,
        'tranche' => Tranche::Statutory, 'days' => -3, 'recorded_by' => $this->user->id, 'authorized_by' => null, 'leave_request_id' => null,
        'note' => 'Días tomados antes de usar el sistema',
    ])->and(leftOf($this->period))->toBe([4, 3]);
});

it('adjusts in favor with no ceiling, on the tranche it is told', function (): void {
    resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Extra, 10, 'Reconocimiento', $this->user->id);

    expect(leftOf($this->period))->toBe([7, 13]);
});

it('takes a tranche down to zero and never below', function (): void {
    expect(adjustmentErrors(fn () => resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, -8, 'Corrección', $this->user->id)))
        ->toBe(['days' => ['No puede quedar bajo cero: a ese tramo le quedan 7 días.']])
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(0);

    resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, -7, 'Corrección', $this->user->id);
    expect(leftOf($this->period))->toBe([0, 3]);
});

it('measures the floor against the tranche adjusted, not against the whole period', function (): void {
    // 10 days are left in all, but only 3 of them are extra.
    expect(adjustmentErrors(fn () => resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Extra, -4, 'Corrección', $this->user->id)))
        ->toBe(['days' => ['No puede quedar bajo cero: a ese tramo le quedan 3 días.']])
        ->and(leftOf($this->period))->toBe([7, 3]);
});

it('does not count the periods of other years or of other people against the floor', function (): void {
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->period->employee_id, 'years_of_service' => 2, 'anniversary_on' => '2027-03-15']);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id]);

    expect(adjustmentErrors(fn () => resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, -8, 'Corrección', $this->user->id)))->toHaveKey('days');
});

it('refuses an adjustment without a note or of zero days', function (int $days, string $note, array $expected): void {
    expect(adjustmentErrors(fn () => resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, $days, $note, $this->user->id)))->toBe($expected)
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(0);
})->with([
    'no note' => [1, '', ['note' => ['Explica el motivo del ajuste.']]],
    'blank note' => [1, "  \n ", ['note' => ['Explica el motivo del ajuste.']]],
    'zero days' => [0, 'Corrección', ['days' => ['El ajuste no puede ser de 0 días.']]],
]);

it('keeps the note as written, without the spaces around it', function (): void {
    expect(resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, 1, '  Corrección  ', $this->user->id)->note)->toBe('Corrección');
});

it('does not adjust the period of another company', function (): void {
    $ofOther = LeaveEntitlement::factory()->withGrant()->create();

    expect(fn () => resolve(AdjustBalance::class)->handle($ofOther->id, Tranche::Statutory, 1, 'Corrección', $this->user->id))->toThrow(ModelNotFoundException::class)
        ->and(LeaveEntry::query()->withoutGlobalScopes()->where('kind', EntryKind::Adjustment)->count())->toBe(0);
});

it('holds the lock of the person while it reads the balance and writes', function (): void {
    DB::enableQueryLog();
    resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, -1, 'Corrección', $this->user->id);
    $queries = collect(DB::getQueryLog())->map(fn (array $query): string => $query['query']);
    DB::disableQueryLog();

    $lock = $queries->search(fn (string $sql): bool => str_contains($sql, 'pg_advisory_xact_lock'));
    $sum = $queries->search(fn (string $sql): bool => str_contains($sql, 'sum('));
    $insert = $queries->search(fn (string $sql): bool => str_starts_with($sql, 'insert into "leave_entries"'));

    expect($lock)->toBeInt()->and($sum)->toBeGreaterThan($lock)->and($insert)->toBeGreaterThan($sum);
});

it('logs and audits the adjustment, and the note reaches neither', function (): void {
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    $entry = resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Extra, -2, 'Nota privada de RH', $this->user->id);

    $context = ['entry_id' => $entry->id, 'entitlement_id' => $this->period->id, 'employee_id' => $this->period->employee_id, 'tranche' => 'extra', 'days' => -2];
    $audit = AuditEntry::query()->where('event', 'leave.balance_adjusted')->sole();
    expect($lines)->toContain('leave.balance_adjusted '.json_encode($context, JSON_THROW_ON_ERROR))
        ->and(implode("\n", $lines))->not->toContain('Nota privada')
        ->and($audit->subject_id)->toBe($this->period->id)
        ->and($audit->metadata)->toEqual(['entry_id' => $entry->id, 'employee_id' => $this->period->employee_id, 'tranche' => 'extra', 'days' => -2]);
});

it('writes nothing when the adjustment is refused', function (): void {
    Log::spy();

    adjustmentErrors(fn () => resolve(AdjustBalance::class)->handle($this->period->id, Tranche::Statutory, -8, 'Corrección', $this->user->id));

    Log::shouldNotHaveReceived('info');
    expect(AuditEntry::query()->where('event', 'leave.balance_adjusted')->count())->toBe(0);
});
