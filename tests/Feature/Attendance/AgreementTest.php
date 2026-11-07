<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\AcceptAgreement;
use App\Modules\Attendance\Exceptions\AgreementOutdated;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceAgreement;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

mutates(CurrentAgreement::class, AcceptAgreement::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('writes version 1 of the agreement on first use, in plain words, with what is recorded, who sees it and for how long', function (): void {
    $agreement = resolve(CurrentAgreement::class)->get();

    expect($agreement)->version->toBe(1)->tenant_id->toBe($this->tenant->id)
        ->and($agreement->body)
        ->toContain('inicio')->toContain('fin')->toContain('pausas')
        ->toContain('coordenadas')->toContain('solo en el momento en que usted marca')
        ->toContain('Recursos Humanos')
        ->toContain('corrección')
        ->toContain('12 meses')
        ->toContain('prueba plena')->toContain('132, fracción XXXIV')
        ->and(resolve(CurrentAgreement::class)->get()->id)->toBe($agreement->id)
        ->and(AttendanceAgreement::query()->count())->toBe(1);
});

it('says the retention of the company\'s policy, and writes a new version when it changes, keeping the old one', function (): void {
    AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'retention_months' => 24]);
    $first = resolve(CurrentAgreement::class)->get();

    AttendancePolicy::query()->sole()->update(['retention_months' => 36]);
    $second = resolve(CurrentAgreement::class)->get();

    expect($first)->version->toBe(1)->body->toContain('24 meses')
        ->and($second)->version->toBe(2)->body->toContain('36 meses')
        ->and($first->fresh())->body->toContain('24 meses');
});

it('keeps each company\'s own agreement', function (): void {
    $mine = resolve(CurrentAgreement::class)->get();
    workIn(Tenant::factory()->create());

    expect(resolve(CurrentAgreement::class)->get())->version->toBe(1)->id->not->toBe($mine->id);
});

it('records that a person accepted the current version, on a device, and when, logging ids only', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $deviceId = (string) Str::uuid();
    Log::spy();

    $acceptance = resolve(AcceptAgreement::class)->handle($ana->id, $deviceId, 1);

    expect($acceptance)->employee_id->toBe($ana->id)->device_id->toBe($deviceId)->tenant_id->toBe($this->tenant->id)
        ->and($acceptance->agreement_id)->toBe(AttendanceAgreement::query()->sole()->id)
        ->and($acceptance->accepted_at->toIso8601String())->toBe('2026-11-07T15:00:00+00:00');
    Log::shouldHaveReceived('info')->with('attendance.agreement_accepted', ['acceptance_id' => $acceptance->id, 'employee_id' => $ana->id, 'device_id' => $deviceId, 'version' => 1]);
});

it('does not record an acceptance of a version that is no longer the current one', function (): void {
    $ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'retention_months' => 24]);
    resolve(CurrentAgreement::class)->get();
    AttendancePolicy::query()->sole()->update(['retention_months' => 36]);

    expect(fn () => resolve(AcceptAgreement::class)->handle($ana->id, (string) Str::uuid(), 1))->toThrow(AgreementOutdated::class)
        ->and(fn () => resolve(AcceptAgreement::class)->handle($ana->id, (string) Str::uuid(), 3))->toThrow(AgreementOutdated::class)
        ->and(AgreementAcceptance::query()->count())->toBe(0)
        ->and(resolve(AcceptAgreement::class)->handle($ana->id, (string) Str::uuid(), 2)->agreement_id)->toBe(AttendanceAgreement::query()->where('version', 2)->sole()->id);
});

it('never changes nor deletes the text a person accepted, nor the acceptance', function (string $sql): void {
    $ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    resolve(AcceptAgreement::class)->handle($ana->id, (string) Str::uuid(), 1);

    expect(fn () => DB::statement($sql))->toThrow(QueryException::class, 'append-only');
})->with([
    'edit the text' => ["UPDATE attendance_agreements SET body = 'otro'"],
    'delete the text' => ['DELETE FROM attendance_agreements'],
    'edit an acceptance' => ['UPDATE attendance_agreement_acceptances SET accepted_at = now()'],
    'delete an acceptance' => ['DELETE FROM attendance_agreement_acceptances'],
]);
