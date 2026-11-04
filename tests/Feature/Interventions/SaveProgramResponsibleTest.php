<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Interventions\Actions\SaveProgramResponsible;
use App\Modules\Interventions\Models\Program;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(SaveProgramResponsible::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->program = Program::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('names a member of the company, by their own name', function (): void {
    $member = memberOf($this->tenant, TenantRole::Hr);
    $member->update(['name' => 'Hugo RH']);

    $program = resolve(SaveProgramResponsible::class)->handle($this->program->id, $member->id, 'Otro');

    expect($program->responsible_user_id)->toBe($member->id)
        ->and($program->responsible_name)->toBe('Hugo RH');
});

it('names a person from outside the company by a free name', function (): void {
    $program = resolve(SaveProgramResponsible::class)->handle($this->program->id, null, '  Dra. Laura Méndez ');

    expect($program->responsible_user_id)->toBeNull()
        ->and($program->responsible_name)->toBe('Dra. Laura Méndez');
});

it('replaces a member with a free name', function (): void {
    $member = memberOf($this->tenant, TenantRole::Hr);
    resolve(SaveProgramResponsible::class)->handle($this->program->id, $member->id, null);

    $program = resolve(SaveProgramResponsible::class)->handle($this->program->id, null, 'Dra. Laura Méndez');

    expect($program->responsible_user_id)->toBeNull()
        ->and($program->responsible_name)->toBe('Dra. Laura Méndez');
});

it('needs a member or a name, and a member of this company', function (): void {
    $stranger = memberOf(Tenant::factory()->create(), TenantRole::Hr);

    foreach ([[null, null], [null, '  '], [$stranger->id, null], [999999, null]] as [$user, $name]) {
        expect(fn () => resolve(SaveProgramResponsible::class)->handle($this->program->id, $user, $name))->toThrow(ValidationException::class);
    }

    expect($this->program->fresh()?->responsible_name)->toBeNull();
});

it('does not find the Programa of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Program::factory()->create(['tenant_id' => $other->id]);

    expect(fn () => resolve(SaveProgramResponsible::class)->handle($foreign->id, null, 'Alguien'))->toThrow(ModelNotFoundException::class);
});

it('logs and audits the change without the name', function (): void {
    Log::spy();

    resolve(SaveProgramResponsible::class)->handle($this->program->id, null, 'Dra. Laura Méndez');

    Log::shouldHaveReceived('info')->with('intervention.program_updated', ['program_id' => $this->program->id, 'responsible_is_member' => false])->once();
    $entry = AuditEntry::query()->where('event', 'interventions.responsible_changed')->sole();
    expect($entry->subject_id)->toBe($this->program->id)
        ->and($entry->metadata)->toBe(['campaign_id' => $this->program->campaign_id, 'responsible_is_member' => false]);
});
