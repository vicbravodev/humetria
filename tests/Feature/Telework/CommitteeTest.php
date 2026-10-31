<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Instruments\Contracts\Nom037Checklist;
use App\Modules\Telework\Actions\AssignCommitteeMember;
use App\Modules\Telework\Actions\RegisterCommittee;
use App\Modules\Telework\Actions\UnassignCommitteeMember;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\QuestionOrigin;
use App\Modules\Telework\Http\Controllers\CommitteeController;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Support\CommitteeGate;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RegisterCommittee::class, AssignCommitteeMember::class, UnassignCommitteeMember::class, CommitteeGate::class, CommitteeController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    $this->actingAs($this->hr);
});

/** @return array<string, mixed> */
function committeeForm(array $overrides = []): array
{
    return ['constituted_on' => '2026-09-15', 'member_ids' => [test()->cora->id], ...$overrides];
}

/** A real upload (not the fake one, whose type comes from its extension): the server sniffs the content. */
function upload(string $content, string $name = 'acta.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'act');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function pdf(): UploadedFile
{
    return upload("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
}

function registerCommittee(array $overrides = [], ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    $center ??= test()->center;

    return test()->post(route('tenant.telework.committee.store', [test()->tenant, $center->id]), committeeForm($overrides));
}

it('registers the committee with its act date, members and a draft built from the base list', function (): void {
    registerCommittee()->assertSessionHasNoErrors()->assertRedirect(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]));

    workIn($this->tenant);
    $committee = SafetyCommittee::query()->sole();
    $checklist = Checklist::query()->sole();
    $questions = ChecklistQuestion::query()->where('checklist_id', $checklist->id)->orderBy('position')->get();
    $items = resolve(Nom037Checklist::class)->items();

    expect($committee->work_center_id)->toBe($this->center->id)
        ->and($committee->constituted_on->toDateString())->toBe('2026-09-15')
        ->and($committee->act_path)->toBeNull()
        ->and($committee->created_by)->toBe($this->hr->id)
        ->and(CommitteeAssignment::query()->pluck('user_id')->all())->toBe([$this->cora->id])
        ->and($checklist->work_center_id)->toBe($this->center->id)
        ->and($checklist->version)->toBe(1)
        ->and($checklist->status)->toBe(ChecklistStatus::Draft)
        ->and($checklist->published_at)->toBeNull()
        ->and($checklist->published_by)->toBeNull()
        ->and($questions)->toHaveCount(122)
        ->and($questions->pluck('position')->all())->toBe(range(1, 122));

    foreach ($items as $index => $item) {
        $question = $questions[$index];
        expect([$question->code, $question->section, $question->heading, $question->text, $question->favorable, $question->requirement, $question->sensitive, $question->base_code, $question->origin])
            ->toBe([$item->code, $item->section, $item->heading, $item->text, $item->favorable, $item->requirement, $item->sensitive, $item->code, $item->fromDof ? QuestionOrigin::Dof : QuestionOrigin::Humetria]);
    }
});

it('requires the act date and at least one member', function (array $overrides, string $field): void {
    registerCommittee($overrides)->assertSessionHasErrors($field);

    workIn($this->tenant);
    expect(SafetyCommittee::query()->count())->toBe(0)->and(Checklist::query()->count())->toBe(0);
})->with([
    'no date' => [['constituted_on' => ''], 'constituted_on'],
    'bad date' => [['constituted_on' => 'ayer'], 'constituted_on'],
    'future date' => [['constituted_on' => '2999-01-01'], 'constituted_on'],
    'no members' => [['member_ids' => []], 'member_ids'],
    'members not an array' => [['member_ids' => 'x'], 'member_ids'],
]);

it('names the fields in Spanish', function (): void {
    registerCommittee(['member_ids' => []])->assertSessionHasErrors(['member_ids' => 'El campo integrantes es obligatorio.']);
    registerCommittee(['constituted_on' => ''])->assertSessionHasErrors(['constituted_on' => 'El campo fecha del acta es obligatorio.']);
});

it('accepts today as the act date', function (): void {
    registerCommittee(['constituted_on' => now()->toDateString()])->assertSessionHasNoErrors();
});

it('rejects a member of another company, a person who cannot review and a repeated id', function (): void {
    $foreign = memberOf(Tenant::factory()->create(), TenantRole::Hr);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    registerCommittee(['member_ids' => [$foreign->id]])->assertSessionHasErrors('member_ids.0');
    registerCommittee(['member_ids' => [$viewer->id]])->assertSessionHasErrors('member_ids.0');
    registerCommittee(['member_ids' => [$this->cora->id, $this->cora->id]])->assertSessionHasErrors('member_ids.0');
    registerCommittee(['member_ids' => ['x']])->assertSessionHasErrors('member_ids.0');

    workIn($this->tenant);
    expect(SafetyCommittee::query()->count())->toBe(0);
});

it('registers one committee per work center', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    registerCommittee(['member_ids' => [$this->hr->id]])->assertSessionHasErrors('work_center');

    workIn($this->tenant);
    expect(SafetyCommittee::query()->count())->toBe(1)->and(Checklist::query()->count())->toBe(1)->and(CommitteeAssignment::query()->count())->toBe(1);

    $other = WorkCenter::factory()->for($this->tenant)->create();
    registerCommittee([], $other)->assertSessionHasNoErrors();
    expect(SafetyCommittee::query()->count())->toBe(2);
});

it('turns a race on the one-per-center rule into a validation error', function (): void {
    workIn($this->tenant);
    $action = resolve(RegisterCommittee::class);
    $action->handle($this->center->id, Carbon\CarbonImmutable::parse('2026-09-15'), null, [$this->cora->id], $this->hr->id);

    // Same call again, without the request's own check: the database's unique index is what refuses.
    expect(fn () => $action->handle($this->center->id, Carbon\CarbonImmutable::parse('2026-09-15'), null, [$this->cora->id], $this->hr->id))
        ->toThrow(Illuminate\Validation\ValidationException::class);
    expect(Checklist::query()->count())->toBe(1);
});

it('stores the act PDF on the private disk', function (): void {
    Storage::fake('local');

    registerCommittee(['act' => pdf()])->assertSessionHasNoErrors();

    workIn($this->tenant);
    $path = SafetyCommittee::query()->sole()->act_path;

    expect($path)->toBeString()->and($path)->toStartWith("committees/{$this->tenant->id}/{$this->center->id}/")->and($path)->toEndWith('.pdf');
    Storage::disk('local')->assertExists((string) $path);
});

it('rejects an act that is not a PDF by its content, whatever its extension', function (): void {
    Storage::fake('local');

    registerCommittee(['act' => upload('<?php echo 1;')])->assertSessionHasErrors('act');
    registerCommittee(['act' => upload(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true), 'acta.pdf')])->assertSessionHasErrors('act');

    workIn($this->tenant);
    expect(SafetyCommittee::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects an act larger than the configured limit', function (): void {
    Storage::fake('local');
    config()->set('telework.max_document_kb', 1);
    $big = upload("%PDF-1.4\n".str_repeat('a', 2048));

    registerCommittee(['act' => $big])->assertSessionHasErrors('act');
});

it('keeps the committee out of the disk when registering fails', function (): void {
    Storage::fake('local');
    registerCommittee()->assertSessionHasNoErrors();
    registerCommittee(['act' => pdf()])->assertSessionHasErrors('work_center');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('assigns and unassigns members', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $ana = memberOf($this->tenant, TenantRole::Owner);
    $assign = fn (int $id) => $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $this->center->id]), ['user_id' => $id]);

    $assign($ana->id)->assertSessionHasNoErrors()->assertRedirect(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]));
    $assign($ana->id)->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(CommitteeAssignment::query()->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->cora->id, $ana->id])->sort()->values()->all());

    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $this->cora->id]))
        ->assertRedirect(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]));

    expect(CommitteeAssignment::query()->pluck('user_id')->all())->toBe([$ana->id]);
});

it('rejects assigning someone who is not a reviewer of this company', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $assign = fn (mixed $id) => $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $this->center->id]), ['user_id' => $id]);

    $assign(memberOf(Tenant::factory()->create(), TenantRole::Hr)->id)->assertSessionHasErrors('user_id');
    $assign(memberOf($this->tenant, TenantRole::Viewer)->id)->assertSessionHasErrors('user_id');
    $assign(null)->assertSessionHasErrors('user_id');
    $assign('x')->assertSessionHasErrors('user_id');

    workIn($this->tenant);
    expect(CommitteeAssignment::query()->count())->toBe(1);
});

it('lets the last member go and shows the center without an active committee', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $this->cora->id]))->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(CommitteeAssignment::query()->count())->toBe(0)->and(SafetyCommittee::query()->count())->toBe(1);

    $this->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('committee.members', [])->where('committee.constitutedOn', '2026-09-15'));
});

it('answers 404 for unassigning someone who is not assigned or a center without committee', function (): void {
    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $this->cora->id]))->assertNotFound();
    $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $this->center->id]), ['user_id' => $this->cora->id])->assertNotFound();

    registerCommittee()->assertSessionHasNoErrors();
    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $this->hr->id]))->assertNotFound();
});

it('shows the page of a center with and without committee', function (): void {
    $this->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('telework/committee')
            ->where('workCenter', ['id' => $this->center->id, 'name' => $this->center->name])
            ->where('committee', null)
            ->where('canManage', true)
            ->has('candidates', 2)
            ->where('candidates.0.name', fn (string $name): bool => $name !== ''));

    Storage::fake('local');
    registerCommittee(['act' => pdf()])->assertSessionHasNoErrors();

    $this->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('committee.constitutedOn', '2026-09-15')
            ->where('committee.hasAct', true)
            ->has('committee.members', 1)
            ->where('committee.members.0', ['id' => $this->cora->id, 'name' => $this->cora->name, 'email' => $this->cora->email, 'role' => 'Comisión de seguridad e higiene', 'active' => true])
            ->has('candidates', 1));
});

it('lets a committee member see only the centers where they are assigned, without managing', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $this->actingAs($this->cora);

    $this->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->get(route('tenant.telework.committee.show', [$this->tenant, $other->id]))->assertNotFound();
    registerCommittee([], $other)->assertForbidden();
    $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $this->center->id]), ['user_id' => $this->hr->id])->assertForbidden();
    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $this->cora->id]))->assertForbidden();
});

it('lets a viewer nowhere and sends a guest to sign in', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    $this->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))->assertNotFound();
    registerCommittee()->assertForbidden();

    auth()->logout();
    $this->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))->assertRedirect(route('login'));
});

it('lets an owner manage the committee', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));

    registerCommittee()->assertSessionHasNoErrors();
    $this->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))->assertOk();
});

it('keeps each company to its own centers: another company, an archived center or a bad id is a 404', function (): void {
    $other = Tenant::factory()->create();
    $foreignCenter = WorkCenter::factory()->for($other)->create();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
    registerCommittee()->assertSessionHasNoErrors();

    foreach ([$foreignCenter->id, $archived->id, 'no-es-uuid', (string) Illuminate\Support\Str::uuid()] as $id) {
        $this->get(route('tenant.telework.committee.show', [$this->tenant, $id]))->assertNotFound();
        $this->post(route('tenant.telework.committee.store', [$this->tenant, $id]), committeeForm())->assertNotFound();
        $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $id]), ['user_id' => $this->hr->id])->assertNotFound();
        $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $id, $this->cora->id]))->assertNotFound();
    }

    workIn($other);
    expect(SafetyCommittee::query()->count())->toBe(0)->and(Checklist::query()->count())->toBe(0)->and(CommitteeAssignment::query()->count())->toBe(0);
    // A member of the other company cannot reach this company's page either.
    $this->actingAs(memberOf($other, TenantRole::Owner))->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))->assertNotFound();
});

it('scopes the committee, its assignments, checklists and questions to the current company', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $other = Tenant::factory()->create();

    workIn($other);
    expect(SafetyCommittee::query()->count())->toBe(0)
        ->and(CommitteeAssignment::query()->count())->toBe(0)
        ->and(Checklist::query()->count())->toBe(0)
        ->and(ChecklistQuestion::query()->count())->toBe(0);

    workIn($this->tenant);
    expect(ChecklistQuestion::query()->count())->toBe(122);
});

it('answers who is on the committee of a center and which centers a person sits on', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $second = WorkCenter::factory()->for($this->tenant)->create();
    registerCommittee(['member_ids' => [$this->cora->id, $this->hr->id]], $second)->assertSessionHasNoErrors();
    $gate = resolve(CommitteeGate::class);

    expect($gate->isMember($this->cora->id, $this->center->id))->toBeTrue()
        ->and($gate->isMember($this->hr->id, $this->center->id))->toBeFalse()
        ->and($gate->isMember($this->hr->id, $second->id))->toBeTrue()
        ->and($gate->isMember($this->cora->id, (string) Illuminate\Support\Str::uuid()))->toBeFalse()
        ->and($gate->workCenterIds($this->cora->id))->toEqualCanonicalizing([$this->center->id, $second->id])
        ->and($gate->workCenterIds($this->hr->id))->toBe([$second->id])
        ->and($gate->workCenterIds(User::factory()->create()->id))->toBe([]);
});

it('does not see the assignments of another company', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    workIn(Tenant::factory()->create());

    expect(resolve(CommitteeGate::class)->isMember($this->cora->id, $this->center->id))->toBeFalse()
        ->and(resolve(CommitteeGate::class)->workCenterIds($this->cora->id))->toBe([]);
});

it('records registering, assigning and unassigning in the audit trail and the log, with ids only', function (): void {
    Log::spy();
    registerCommittee()->assertSessionHasNoErrors();
    $ana = memberOf($this->tenant, TenantRole::Owner);
    $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $this->center->id]), ['user_id' => $ana->id]);
    $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $this->center->id]), ['user_id' => $ana->id]);
    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $ana->id]));

    $registered = AuditEntry::query()->where('event', 'telework.committee_registered')->sole();
    $assigned = AuditEntry::query()->where('event', 'telework.committee_member_assigned')->sole();
    $unassigned = AuditEntry::query()->where('event', 'telework.committee_member_unassigned')->sole();

    expect($registered->actor_id)->toBe($this->hr->id)
        ->and($registered->tenant_id)->toBe($this->tenant->id)
        ->and($registered->metadata)->toEqual(['work_center_id' => $this->center->id, 'member_ids' => [$this->cora->id]])
        ->and($assigned->metadata)->toEqual(['work_center_id' => $this->center->id, 'user_id' => $ana->id])
        ->and($unassigned->metadata)->toEqual(['work_center_id' => $this->center->id, 'user_id' => $ana->id]);
    Log::shouldHaveReceived('info')->with('telework.committee_registered', ['work_center_id' => $this->center->id, 'member_ids' => [$this->cora->id]])->once();
    Log::shouldHaveReceived('info')->with('telework.committee_member_assigned', ['work_center_id' => $this->center->id, 'user_id' => $ana->id])->once();
    Log::shouldHaveReceived('info')->with('telework.committee_member_unassigned', ['work_center_id' => $this->center->id, 'user_id' => $ana->id])->once();
});

it('refuses in the database a second committee per center, a repeated assignment and a repeated draft version', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    workIn($this->tenant);

    $attempts = [
        'safety_committees_work_center_unique' => fn () => SafetyCommittee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]),
        'committee_assignments_unique' => fn () => CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]),
        'checklists_version_unique' => fn () => Checklist::query()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'version' => 1, 'status' => ChecklistStatus::Draft]),
        'checklist_questions_code_unique' => fn () => ChecklistQuestion::query()->create([...ChecklistQuestion::query()->firstOrFail()->only(['tenant_id', 'checklist_id', 'section', 'code', 'heading', 'text', 'origin', 'favorable', 'sensitive']), 'position' => 999]),
    ];

    foreach ($attempts as $constraint => $attempt) {
        // A savepoint each: a refused statement aborts the transaction the test runs in.
        expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class, $constraint);
    }
});

it('stops a demoted or removed member from being on the committee, yet shows the seat to RH so it can end it', function (): void {
    registerCommittee()->assertSessionHasNoErrors();
    $gone = memberOf($this->tenant, TenantRole::Committee);
    $this->post(route('tenant.telework.committee.members.store', [$this->tenant, $this->center->id]), ['user_id' => $gone->id])->assertSessionHasNoErrors();
    workIn($this->tenant);
    $roles = resolve(App\Modules\Tenancy\Support\CompanyRoles::class);
    $gate = resolve(CommitteeGate::class);
    expect($gate->isMember($this->cora->id, $this->center->id))->toBeTrue();

    // Cora is demoted to viewer; the other one is taken out of the company.
    $roles->assign($this->cora, $this->tenant, TenantRole::Viewer);
    $roles->remove($gone, $this->tenant);

    expect($gate->isMember($this->cora->id, $this->center->id))->toBeFalse()
        ->and($gate->workCenterIds($this->cora->id))->toBe([])
        ->and($gate->isMember($gone->id, $this->center->id))->toBeFalse()
        ->and($gate->workCenterIds($gone->id))->toBe([]);

    $this->actingAs($this->cora)->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))->assertNotFound();

    $this->actingAs($this->hr)->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('committee.members', 2)
            ->where('committee.members', fn ($members): bool => collect($members)->every(fn (array $member): bool => $member['active'] === false && $member['role'] === 'Sin acceso: ya no tiene un rol de la Comisión')
                && collect($members)->pluck('id')->sort()->values()->all() === collect([$this->cora->id, $gone->id])->sort()->values()->all()));

    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $this->cora->id]))->assertSessionHasNoErrors();
    $this->delete(route('tenant.telework.committee.members.destroy', [$this->tenant, $this->center->id, $gone->id]))->assertSessionHasNoErrors();
    expect(CommitteeAssignment::query()->count())->toBe(0);
});

it('does not send the company people to a committee member', function (): void {
    registerCommittee()->assertSessionHasNoErrors();

    $this->actingAs($this->cora)->get(route('tenant.telework.committee.show', [$this->tenant, $this->center->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('candidates')->where('canManage', false));
});

it('removes the act file when registering fails after storing it', function (): void {
    Storage::fake('local');
    workIn($this->tenant);
    Log::partialMock()->shouldReceive('info')->with('telework.committee_registered', Mockery::any())->andThrow(new RuntimeException('boom'));

    expect(fn () => resolve(RegisterCommittee::class)->handle($this->center->id, Carbon\CarbonImmutable::parse('2026-09-15'), pdf()->getPathname(), [$this->cora->id], $this->hr->id))
        ->toThrow(RuntimeException::class, 'boom');

    expect(Storage::disk('local')->allFiles())->toBe([])->and(SafetyCommittee::query()->count())->toBe(0);
});
