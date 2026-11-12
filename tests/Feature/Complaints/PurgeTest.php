<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\PurgeComplaints;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(PurgeComplaints::class);

const SECRET = 'El jefe de turno me humilla frente a todos';

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['retention_months' => 24]);
    $this->plant = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    workIn($this->tenant);
});

/**
 * A closed case with everything a case has.
 *
 * @param  array<string, mixed>  $attributes
 */
function closedCase(object $test, string $closedOn, array $attributes = []): Complaint
{
    $complaint = Complaint::factory()->closed()->create([
        'tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, 'reference' => 'OLD12345', 'access_key_hash' => hash('sha256', 'OLDKEY'),
        'type' => 'workplace_violence', 'category' => 'mistreatment', 'respondent_kind' => 'hr', 'work_center_id' => $test->plant->id,
        'identity_mode' => 'confidential', 'reporter_name' => 'Rosa Aguilar', 'reporter_contact' => 'Cel. 442 000', 'description' => SECRET, 'respondent_text' => 'Don Remigio',
        'received_on' => '2024-09-17', 'closed_on' => $closedOn, 'closed_at' => $closedOn.' 12:00:00', 'acknowledged_at' => '2024-09-18 12:00:00', ...$attributes,
    ]);
    ComplaintAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $test->owner->id]);
    ComplaintEntry::factory()->create(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'position' => 1, 'kind' => 'internal_note', 'body' => 'Nota secreta', 'author_id' => $test->owner->id]);
    ComplaintEntry::factory()->fromReporter('2024-09-18')->create(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'position' => 2, 'body' => 'Mensaje secreto']);

    return $complaint;
}

function purge(): int
{
    return resolve(PurgeComplaints::class)->handle();
}

it('purges the cases of a company that does not have the module too: retention outlives the plan', function (): void {
    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->tenant->id, ['nom037'], \Carbon\CarbonImmutable::now(config('app.display_timezone')));
    closedCase($this, '2024-11-19');

    expect(purge())->toBe(1);
});

it('purges the content of a case once its retention is over, and leaves a skeleton with no personal data', function (): void {
    Log::spy();
    $complaint = closedCase($this, '2024-11-19');

    expect(purge())->toBe(1);

    $row = (array) DB::table('complaints')->where('id', $complaint->id)->first();
    expect($row['description'])->toBeNull()->and($row['respondent_text'])->toBeNull()->and($row['reporter_name'])->toBeNull()->and($row['reporter_contact'])->toBeNull()->and($row['involved_text'])->toBeNull()
        ->and($row['work_center_id'])->toBeNull()->and($row['acknowledged_at'])->toBeNull()->and($row['occurred_on'])->toBeNull()
        ->and($row['identity_mode'])->toBe('anonymous')->and($row['respondent_kind'])->toBe('unknown')
        ->and($row['purged_on'])->toBe('2026-11-20')
        ->and($row['type'])->toBe('workplace_violence')->and($row['category'])->toBe('mistreatment')->and($row['resolution'])->toBe('measures_taken')
        ->and($row['received_on'])->toBe('2024-09-01')->and($row['closed_on'])->toBe('2024-11-19')->and($row['status'])->toBe('closed');
    expect($row['reference'])->not->toBe('OLD12345')->and($row['access_key_hash'])->not->toBe(hash('sha256', 'OLDKEY'));
    expect(ComplaintEntry::query()->where('complaint_id', $complaint->id)->count())->toBe(0)
        ->and(ComplaintAssignment::query()->where('complaint_id', $complaint->id)->count())->toBe(0);
    Log::shouldHaveReceived('info')->with('complaints.purged', ['count' => 1])->once();
    $audit = AuditEntry::query()->where('event', 'complaint.purged')->sole();
    expect($audit->subject_id)->toBe($complaint->id)->and($audit->actor_id)->toBeNull();
});

it('leaves no word of the case anywhere in the database once it is purged', function (): void {
    closedCase($this, '2024-11-19');

    purge();

    foreach (['humilla', 'Rosa Aguilar', 'Remigio', 'Nota secreta', 'Mensaje secreto', 'OLD12345'] as $needle) {
        $found = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->filter(fn (object $table): bool => DB::selectOne('select count(*) as found from "'.$table->table_name.'" t where t::text like ?', ['%'.$needle.'%'])->found > 0)
            ->pluck('table_name')->all();

        expect($found)->toBe([], "«{$needle}» still in: ".implode(', ', $found));
    }
});

it('stops the old reference and key from opening the purged case', function (): void {
    closedCase($this, '2024-11-19');
    purge();

    $this->postJson('/c/'.$this->channel->access_code.'/follow-up', ['reference' => 'OLD12345', 'key' => 'OLDKEY'])->assertUnprocessable();
});

it('purges at exactly the retention and not a day before', function (string $closedOn, bool $purged): void {
    $complaint = closedCase($this, $closedOn);

    purge();

    expect($complaint->refresh()->purged_on !== null)->toBe($purged);
})->with([
    '24 months ago, the day' => ['2024-11-20', true],
    '23 months and 29 days ago' => ['2024-11-21', false],
    'a year ago' => ['2025-11-19', false],
    'three years ago' => ['2023-11-19', true],
]);

it('takes the retention of the company\'s channel, between its limits', function (int $months, string $closedOn, bool $purged): void {
    $this->channel->forceFill(['retention_months' => $months])->save();
    $complaint = closedCase($this, $closedOn);

    purge();

    expect($complaint->refresh()->purged_on !== null)->toBe($purged);
})->with([
    '12 months: a year and a day' => [12, '2025-11-19', true],
    '12 months: 11 months' => [12, '2025-12-19', false],
    '60 months: four years' => [60, '2022-11-19', false],
    '60 months: five years' => [60, '2021-11-19', true],
]);

it('does not purge a case under legal hold, one still open or one already purged', function (): void {
    $held = closedCase($this, '2023-01-01', ['on_hold' => true, 'reference' => 'HELD1234', 'access_key_hash' => hash('sha256', 'HELD')]);
    $open = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'description' => SECRET, 'received_on' => '2020-01-01']);
    $done = closedCase($this, '2023-01-01', ['reference' => 'DONE1234', 'access_key_hash' => hash('sha256', 'DONE')]);

    expect(purge())->toBe(1);
    expect(purge())->toBe(0);

    expect($held->refresh()->description)->toBe(SECRET)->and($open->refresh()->description)->toBe(SECRET)->and($done->refresh()->description)->toBeNull();
    $this->actingAs($this->owner);
    expect(ComplaintEntry::query()->where('complaint_id', $held->id)->count())->toBe(2);
});

it('purges once the hold is lifted', function (): void {
    $held = closedCase($this, '2023-01-01', ['on_hold' => true]);
    purge();
    $held->forceFill(['on_hold' => false])->save();

    expect(purge())->toBe(1);
});

it('purges every company by its own retention, and leaves the others\' cases alone', function (): void {
    $other = Tenant::factory()->create();
    $channel = ComplaintChannel::factory()->forTenant($other->id)->active()->create(['retention_months' => 60]);
    $theirs = Complaint::factory()->closed()->create(['tenant_id' => $other->id, 'channel_id' => $channel->id, 'received_on' => '2024-01-01', 'closed_on' => '2024-11-19', 'closed_at' => '2024-11-19 12:00:00']);
    $mine = closedCase($this, '2024-11-19');

    expect(purge())->toBe(1);

    expect($mine->refresh()->purged_on)->not->toBeNull();
    expect(Complaint::query()->withoutGlobalScopes()->find($theirs->id)?->purged_on)->toBeNull();
});

it('purges even when the company may no longer be used: data does not outlive its time because of a bill', function (array $columns): void {
    $complaint = closedCase($this, '2023-01-01');
    $this->tenant->forceFill($columns)->save();

    expect(purge())->toBe(1);
    expect(Complaint::query()->withoutGlobalScopes()->find($complaint->id)?->purged_on)->not->toBeNull();
})->with([
    'read only' => [['restriction' => 'read_only', 'restriction_reason' => 'Prueba', 'restricted_at' => '2026-11-01 12:00:00+00']],
    'suspended' => [['restriction' => 'suspended', 'restriction_reason' => 'Prueba', 'restricted_at' => '2026-11-01 12:00:00+00']],
    'archived' => [['restriction' => 'archived', 'restriction_reason' => 'Prueba', 'restricted_at' => '2026-11-01 12:00:00+00']],
    'expired' => [['active_until' => '2026-11-01 12:00:00+00']],
]);

it('runs from the command, daily and once, and reports how many went', function (): void {
    closedCase($this, '2023-01-01');

    $this->artisan('complaints:purge')->expectsOutput('Casos purgados: 1')->assertSuccessful();
});

it('purges the notice of a traumatic event too, whose name and date the database demands while the case lives', function (): void {
    $notice = closedCase($this, '2023-01-01', ['type' => 'traumatic_event_notice', 'category' => null, 'occurred_on' => '2022-12-01', 'involved_text' => 'Luis y el jefe de turno', 'reference' => 'NOTICE12', 'access_key_hash' => hash('sha256', 'NOTICE')]);

    expect(purge())->toBe(1);

    $row = (array) DB::table('complaints')->where('id', $notice->id)->first();
    expect($row['reporter_name'])->toBeNull()->and($row['occurred_on'])->toBeNull()->and($row['involved_text'])->toBeNull()->and($row['type'])->toBe('traumatic_event_notice')->and($row['purged_on'])->not->toBeNull();
});
