<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->other = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bodega']);
    $this->small = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Taller']);
    $this->survey = climateResultsSurvey($this->tenant, 12, null, ['area_id' => $this->area->id]);
    foreach ([[$this->other, 8], [$this->small, 3]] as [$area, $count]) {
        Response::factory()->count($count)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'area_id' => $area->id]);
    }
    $this->survey->update(['priorities' => [['driver' => 'recognition', 'level' => 'high'], ['driver' => 'clarity', 'level' => 'medium']]]);
});

function commitmentsUrl(Tenant $tenant, Survey $survey, string $query = ''): string
{
    return route('tenant.climate.commitments.index', [$tenant, $survey]).$query;
}

/** @return array<string, mixed> */
function commitmentPayload(array $overrides = []): array
{
    return [
        'group_type' => 'company',
        'group_id' => null,
        'driver' => 'recognition',
        'heard' => 'Que el buen trabajo no se reconoce.',
        'will_do' => 'Reconocer por escrito cada logro del mes.',
        'owner_name' => 'Ana Torres',
        'due_on' => now()->addMonth()->toDateString(),
        ...$overrides,
    ];
}

it('lets RH write a commitment for the company or a group the results show', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.climate.commitments.store', [$this->tenant, $this->survey]), commitmentPayload())
        ->assertRedirect(commitmentsUrl($this->tenant, $this->survey));
    $this->post(route('tenant.climate.commitments.store', [$this->tenant, $this->survey]), commitmentPayload(['group_type' => 'area', 'group_id' => $this->area->id, 'driver' => null]))
        ->assertRedirect();

    expect(Commitment::query()->count())->toBe(2)
        ->and(Commitment::query()->where('group_type', 'area')->sole()->group_id)->toBe($this->area->id);
});

it('rejects a fourth commitment for a group, a group the results hide and a survey still open', function (): void {
    $this->actingAs($this->hr);
    $store = fn (array $data, ?Survey $survey = null) => $this->post(route('tenant.climate.commitments.store', [$this->tenant, $survey ?? $this->survey]), commitmentPayload($data));

    foreach (range(1, 3) as $ignored) {
        $store([])->assertRedirect();
    }
    $store([])->assertSessionHasErrors('group_id');
    $store(['group_type' => 'area', 'group_id' => $this->small->id])->assertSessionHasErrors('group_id');
    $store([], climateResultsSurvey($this->tenant, 6, closed: false))->assertSessionHasErrors('survey');

    expect(Commitment::query()->count())->toBe(3);
});

it('validates what is written', function (array $bad, string $field): void {
    $this->actingAs($this->hr)->post(route('tenant.climate.commitments.store', [$this->tenant, $this->survey]), commitmentPayload($bad))->assertSessionHasErrors($field);
})->with([
    'no what was heard' => [['heard' => ''], 'heard'],
    'what was heard too long' => [['heard' => str_repeat('a', 501)], 'heard'],
    'no what will be done' => [['will_do' => ''], 'will_do'],
    'what will be done too long' => [['will_do' => str_repeat('a', 501)], 'will_do'],
    'no owner' => [['owner_name' => ''], 'owner_name'],
    'owner too long' => [['owner_name' => str_repeat('a', 121)], 'owner_name'],
    'no date' => [['due_on' => ''], 'due_on'],
    'a date in the past' => [['due_on' => '2020-01-01'], 'due_on'],
    'an unknown theme' => [['driver' => 'luck'], 'driver'],
    'an unknown group' => [['group_type' => 'planet'], 'group_type'],
    'a group id that is not an id' => [['group_type' => 'area', 'group_id' => 'ventas'], 'group_id'],
]);

it('shows every role that views climate the commitments, paginated and filtered by status, and only RH can change them', function (): void {
    Commitment::factory()->count(12)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);
    Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'status' => CommitmentStatus::Done, 'will_do' => 'Hecho y listo']);

    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->actingAs($viewer)->get(commitmentsUrl($this->tenant, $this->survey))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/commitments')
            ->where('available', true)
            ->has('commitments.data', 10)
            ->where('commitments.total', 13)
            ->where('counts', ['all' => 13, 'open' => 12, 'in_progress' => 0, 'done' => 1, 'dropped' => 0])
            ->where('canManage', false)
            ->where('published', false));
    $this->get(commitmentsUrl($this->tenant, $this->survey, '?status=done'))
        ->assertInertia(fn (Assert $page) => $page->has('commitments.data', 1)->where('commitments.data.0.will_do', 'Hecho y listo'));

    $this->post(route('tenant.climate.commitments.store', [$this->tenant, $this->survey]), commitmentPayload())->assertForbidden();
    $commitment = Commitment::query()->firstOrFail();
    $this->put(route('tenant.climate.commitments.update', [$this->tenant, $this->survey, $commitment]), commitmentPayload())->assertForbidden();
    $this->put(route('tenant.climate.commitments.status', [$this->tenant, $this->survey, $commitment]), ['status' => 'done'])->assertForbidden();
});

it('offers the groups the results show, the company first, and the suggested actions of the priorities', function (): void {
    $this->actingAs($this->hr)->get(commitmentsUrl($this->tenant, $this->survey))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->where('groups.0', ['type' => 'company', 'id' => null, 'name' => 'Toda la empresa', 'full' => false])
            ->where('groups', fn ($groups): bool => collect($groups)->pluck('id')->contains($this->area->id) && ! collect($groups)->pluck('id')->contains($this->small->id))
            ->has('suggestions', 2)
            ->where('suggestions.0.driver', 'recognition')
            ->where('suggestions.0.label', 'Reconocimiento')
            ->has('suggestions.0.actions', 2));
});

it('marks a group as full when it has three commitments, and a dropped one does not count', function (): void {
    Commitment::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);

    $this->actingAs($this->hr)->get(commitmentsUrl($this->tenant, $this->survey))->assertInertia(fn (Assert $page) => $page->where('groups.0.full', true));

    Commitment::query()->first()?->update(['status' => CommitmentStatus::Dropped]);
    $this->get(commitmentsUrl($this->tenant, $this->survey))->assertInertia(fn (Assert $page) => $page->where('groups.0.full', false));
});

it('says the commitments wait for the survey to close while it is open', function (): void {
    $open = climateResultsSurvey($this->tenant, 6, closed: false);

    $this->actingAs($this->hr)->get(commitmentsUrl($this->tenant, $open))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('available', false)->where('commitments.total', 0)->where('groups', [])->where('suggestions', []));
});

it('edits and moves a commitment along', function (): void {
    $commitment = Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);
    $this->actingAs($this->hr);

    $this->put(route('tenant.climate.commitments.update', [$this->tenant, $this->survey, $commitment]), commitmentPayload(['will_do' => 'Otra acción']))->assertRedirect();
    $this->put(route('tenant.climate.commitments.status', [$this->tenant, $this->survey, $commitment]), ['status' => 'in_progress'])->assertRedirect();
    $this->put(route('tenant.climate.commitments.status', [$this->tenant, $this->survey, $commitment]), ['status' => 'finished'])->assertSessionHasErrors('status');

    expect($commitment->fresh()?->will_do)->toBe('Otra acción')
        ->and($commitment->fresh()?->status)->toBe(CommitmentStatus::InProgress);
});

it('does not find the commitments of another company, nor a pulse survey, nor a commitment of another survey', function (): void {
    $other = Tenant::factory()->create();
    $surveyOfOther = climateResultsSurvey($other, 6);
    $ofOther = Commitment::factory()->create(['tenant_id' => $other->id, 'survey_id' => $surveyOfOther->id]);
    workIn($this->tenant);
    $pulse = Survey::factory()->closed()->pulse()->create(['tenant_id' => $this->tenant->id]);
    $sibling = climateResultsSurvey($this->tenant, 6);
    $ofSibling = Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $sibling->id]);

    $this->actingAs($this->hr);
    $this->get(commitmentsUrl($this->tenant, $surveyOfOther))->assertNotFound();
    $this->get(commitmentsUrl($this->tenant, $pulse))->assertNotFound();
    $this->post(route('tenant.climate.commitments.store', [$this->tenant, $surveyOfOther]), commitmentPayload())->assertNotFound();
    $this->put(route('tenant.climate.commitments.update', [$this->tenant, $this->survey, $ofOther]), commitmentPayload())->assertNotFound();
    $this->put(route('tenant.climate.commitments.status', [$this->tenant, $this->survey, $ofOther]), ['status' => 'done'])->assertNotFound();
    $this->put(route('tenant.climate.commitments.status', [$this->tenant, $this->survey, $ofSibling]), ['status' => 'done'])->assertNotFound();
    $this->actingAs(User::factory()->create())->get(commitmentsUrl($this->tenant, $this->survey))->assertNotFound();
    expect($ofOther->fresh()?->status)->toBe(CommitmentStatus::Open);
});

it('never puts responses or comments in the props, nor the commitment texts in the logs or the audit trail', function (): void {
    $path = storage_path('logs/commitments-privacy-test.log');
    File::delete($path);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');
    Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'body' => 'Falta personal en la bodega']);
    Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);

    $this->actingAs($this->hr)->post(route('tenant.climate.commitments.store', [$this->tenant, $this->survey]), commitmentPayload(['will_do' => 'Texto secreto de la acción', 'heard' => 'Dato secreto oído']))->assertRedirect();
    $props = Assert::fromTestResponse($this->get(commitmentsUrl($this->tenant, $this->survey))->assertOk())->toArray()['props'];

    expectNoEngagementResponses($props);
    expect(File::get($path))->toContain('engagement.commitment_saved')->not->toContain('secreto');
    expect(json_encode(AuditEntry::query()->pluck('metadata'), JSON_THROW_ON_ERROR))->not->toContain('secreto');
    File::delete($path);
});
