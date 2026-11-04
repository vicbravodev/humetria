<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishContent;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartContentDraft;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Http\Controllers\ContentController;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(StartContentDraft::class, PublishContent::class, ContentController::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    workIn($this->tenant);
});

/** A draft of `$kind` with the words `$text`. */
function contentDraft(DocumentKind $kind, string $text = 'Medidas de la empresa.'): Document
{
    $draft = resolve(StartContentDraft::class)->handle($kind, null);

    return resolve(SaveDraft::class)->handle($draft->id, [['key' => 'body', 'text' => $text, 'items' => []]]);
}

it('starts the draft of each block of 5.7 from a base text: empty, or the definition of 4.7 for the health effects', function (DocumentKind $kind, string $starts): void {
    $draft = resolve(StartContentDraft::class)->handle($kind, $this->hr->id);

    expect($draft->kind)->toBe($kind)
        ->and($draft->status)->toBe(DocumentStatus::Draft)
        ->and($draft->content)->toHaveCount(1)
        ->and($draft->content[0]['key'])->toBe('body')
        ->and($draft->content[0]['heading'])->toBe($kind->label())
        ->and(str_starts_with($draft->content[0]['text'], $starts))->toBeTrue()
        ->and($draft->covers)->toBeNull()
        ->and($draft->template_version)->toBeNull();
})->with([
    'violence measures' => [DocumentKind::ViolenceMeasures, ''],
    'prevention measures' => [DocumentKind::PreventionMeasures, ''],
    'complaint mechanism' => [DocumentKind::ComplaintMechanism, ''],
    'health effects' => [DocumentKind::HealthEffects, 'Los factores de riesgo psicosocial son aquellos que pueden provocar trastornos de ansiedad, no orgánicos del ciclo sueño-vigilia y de estrés grave y de adaptación'],
]);

it('starts one draft per kind and starts a new version from the latest one published', function (): void {
    $first = resolve(StartContentDraft::class)->handle(DocumentKind::ViolenceMeasures, null);
    $again = resolve(StartContentDraft::class)->handle(DocumentKind::ViolenceMeasures, null);
    $other = resolve(StartContentDraft::class)->handle(DocumentKind::PreventionMeasures, null);

    expect($again->id)->toBe($first->id)->and($other->id)->not->toBe($first->id);

    Document::factory()->published()->create(['tenant_id' => $this->tenant->id, 'kind' => DocumentKind::HealthEffects, 'covers' => null, 'version' => 1, 'content' => [['key' => 'body', 'heading' => 'x', 'text' => 'Texto publicado', 'items' => [], 'list_style' => null]]]);

    expect(resolve(StartContentDraft::class)->handle(DocumentKind::HealthEffects, null)->content[0]['text'])->toBe('Texto publicado');
});

it('does not start the policy this way', function (): void {
    expect(fn () => resolve(StartContentDraft::class)->handle(DocumentKind::Policy, null))->toThrow(InvalidArgumentException::class);
});

it('publishes the text as version 1 of its kind for every center in use, without asking anyone to confirm it', function (): void {
    Event::fake([App\Modules\Disclosure\Events\PolicyPublished::class]);
    $draft = contentDraft(DocumentKind::ViolenceMeasures);

    $published = resolve(PublishContent::class)->handle($draft->id, null, $this->hr->id);

    expect($published->version)->toBe(1)->and($published->status)->toBe(DocumentStatus::Published)->and($published->covers)->toBeNull()
        ->and(DocumentCenter::query()->where('document_id', $published->id)->count())->toBe(2);
    Event::assertNotDispatched(App\Modules\Disclosure\Events\PolicyPublished::class);
});

it('numbers each kind apart, and each company apart', function (): void {
    $other = Tenant::factory()->create();
    Document::factory()->published()->create(['tenant_id' => $other->id, 'kind' => DocumentKind::ViolenceMeasures, 'covers' => null, 'version' => 5]);
    resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures)->id, null, $this->hr->id);
    resolve(PublishContent::class)->handle(contentDraft(DocumentKind::PreventionMeasures)->id, null, $this->hr->id);

    $second = resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures, 'Otras medidas.')->id, [$this->north->id], $this->hr->id);

    expect($second->version)->toBe(2)
        ->and(Document::query()->where('kind', DocumentKind::PreventionMeasures)->sole()->version)->toBe(1);
});

it('refuses an empty text, a center that is not in use and the policy, each on its field', function (Closure $attempt, string $field): void {
    try {
        $attempt($this);
        $this->fail('The publication should have been refused.');
    } catch (ValidationException $failure) {
        expect(array_keys($failure->errors()))->toBe([$field]);
    }
})->with([
    'an empty text' => [fn ($test) => resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures, '  ')->id, null, $test->hr->id), 'body'],
    'no centers' => [fn ($test) => resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures)->id, [], $test->hr->id), 'work_center_ids'],
    'a center of another company' => [fn ($test) => resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures)->id, [WorkCenter::factory()->for(Tenant::factory()->create())->create()->id], $test->hr->id), 'work_center_ids'],
]);

it('does not publish a policy draft, a published one or the draft of another company', function (): void {
    $policy = Document::factory()->draft()->policy()->create(['tenant_id' => $this->tenant->id]);
    $published = Document::factory()->published()->create(['tenant_id' => $this->tenant->id, 'kind' => DocumentKind::HealthEffects, 'covers' => null]);
    $foreign = Document::factory()->draft()->create(['tenant_id' => Tenant::factory()->create()->id, 'kind' => DocumentKind::HealthEffects]);

    foreach ([$policy->id, $published->id, $foreign->id] as $id) {
        expect(fn () => resolve(PublishContent::class)->handle($id, null, $this->hr->id))->toThrow(ModelNotFoundException::class);
    }
});

it('audits the publication of any kind without its words, and logs ids and counts', function (): void {
    Log::spy();
    $published = resolve(PublishContent::class)->handle(contentDraft(DocumentKind::HealthEffects, 'Texto que no va a la bitácora')->id, [$this->north->id], $this->hr->id);

    $entry = AuditEntry::query()->where('event', 'disclosure.published')->sole();
    expect($entry->metadata)->toEqual(['document_id' => $published->id, 'kind' => 'health_effects', 'version' => 1, 'work_center_ids' => [$this->north->id]])
        ->and(json_encode($entry->metadata))->not->toContain('bitácora');
    Log::shouldHaveReceived('info')->with('disclosure.published', ['document_id' => $published->id, 'kind' => 'health_effects', 'version' => 1, 'work_center_count' => 1])->once();
});

it('shows each block with the text in force, the draft and where it was published', function (): void {
    resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures, 'Primer texto.')->id, null, $this->hr->id);
    resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures, 'Segundo texto.')->id, [$this->north->id], $this->hr->id);
    resolve(StartContentDraft::class)->handle(DocumentKind::HealthEffects, $this->hr->id);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.contents', $this->tenant))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('disclosure/contents')
            ->where('centersInUse', 2)
            ->where('blocks.0', fn ($block) => $block['kind'] === 'violence_measures' && $block['inciso'] === 'b' && $block['label'] === 'Medidas contra la violencia laboral' && $block['latest']['version'] === 2 && $block['latest']['text'] === 'Segundo texto.' && $block['latest']['centers'] === 1 && $block['latest']['publishedOn'] === '20 nov 2026' && $block['draft'] === null)
            ->where('blocks.3', fn ($block) => $block['kind'] === 'health_effects' && $block['inciso'] === 'f' && $block['latest'] === null && $block['draft']['text'] !== '' && isset($block['draft']['id']) && $block['note'] !== null)
            ->has('blocks', 4));
});

it('lets only whoever manages see and change the contents', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.disclosure.contents', $this->tenant))->assertStatus($status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('starts, saves, publishes and discards a block from the forms', function (): void {
    $route = fn (string $name, mixed ...$parameters): string => route("tenant.disclosure.contents.{$name}", [$this->tenant, ...$parameters]);

    $this->actingAs($this->hr)->post($route('draft.store', 'prevention_measures'))->assertRedirect(route('tenant.disclosure.contents', $this->tenant));
    $draft = Document::query()->where('kind', DocumentKind::PreventionMeasures)->sole();

    $this->actingAs($this->hr)->put($route('draft.update', 'prevention_measures', $draft->id), ['sections' => [['key' => 'body', 'text' => 'Capacitación anual.', 'items' => []]]])->assertSessionHasNoErrors();
    expect($draft->fresh()?->content[0]['text'])->toBe('Capacitación anual.');

    $this->actingAs($this->hr)->post($route('publish', 'prevention_measures', $draft->id), ['all_centers' => true])->assertSessionHasNoErrors();
    expect($draft->fresh()?->version)->toBe(1);

    $other = resolve(StartContentDraft::class)->handle(DocumentKind::ComplaintMechanism, null);
    $this->actingAs($this->hr)->delete($route('draft.destroy', 'complaint_mechanism', $other->id))->assertRedirect(route('tenant.disclosure.contents', $this->tenant));
    expect(Document::query()->where('kind', DocumentKind::ComplaintMechanism)->count())->toBe(0);
});

it('answers on the fields and does not find what is not of that kind or of that company', function (): void {
    $route = fn (string $name, mixed ...$parameters): string => route("tenant.disclosure.contents.{$name}", [$this->tenant, ...$parameters]);
    $draft = contentDraft(DocumentKind::ViolenceMeasures, '');
    $foreign = Document::factory()->draft()->create(['tenant_id' => Tenant::factory()->create()->id, 'kind' => DocumentKind::ViolenceMeasures]);

    $this->actingAs($this->hr)->post($route('publish', 'violence_measures', $draft->id), ['all_centers' => true])->assertSessionHasErrors('body');
    $this->actingAs($this->hr)->post($route('publish', 'violence_measures', $draft->id), ['all_centers' => false, 'work_center_ids' => []])->assertSessionHasErrors('work_center_ids');
    $this->actingAs($this->hr)->post($route('draft.store', 'rules'))->assertNotFound();
    $this->actingAs($this->hr)->post($route('draft.store', 'policy'))->assertNotFound();
    $this->actingAs($this->hr)->put($route('draft.update', 'prevention_measures', $draft->id), ['sections' => [['key' => 'body', 'text' => 'x', 'items' => []]]])->assertNotFound();
    $this->actingAs($this->hr)->put($route('draft.update', 'violence_measures', $foreign->id), ['sections' => [['key' => 'body', 'text' => 'x', 'items' => []]]])->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post($route('draft.store', 'violence_measures'))->assertForbidden();
});

describe('on the board', function (): void {
    /** The row of `$code` of the north plant. @return array<string, mixed>|null */
    function boardRowOf(object $test, string $code): ?array
    {
        $row = null;
        $test->actingAs($test->hr)->get(route('tenant.disclosure.index', $test->tenant))->assertInertia(function (Assert $page) use (&$row, $code): void {
            $row = collect($page->toArray()['props']['centers']['data'])->firstWhere('name', 'Planta Norte')['requirements'];
            $row = collect($row)->firstWhere('code', $code);
        });

        return $row;
    }

    it('is missing a block that has no text, and says so', function (string $code, string $detail): void {
        expect(boardRowOf($this, $code))->toMatchArray(['detail' => $detail])
            ->and(boardRowOf($this, $code)['state']['value'])->toBe('missing');
    })->with([
        'D4' => ['D4', 'Aún no hay información publicada sobre las medidas contra la violencia laboral.'],
        'D5' => ['D5', 'Aún no hay información publicada sobre las medidas y acciones de prevención y control.'],
        'D6' => ['D6', 'Aún no hay información publicada sobre los mecanismos para presentar quejas.'],
        'D8' => ['D8', 'Aún no hay información publicada sobre las posibles alteraciones a la salud.'],
    ]);

    it('is missing a block whose text is published but not in front of the people', function (): void {
        resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures)->id, [$this->north->id], $this->hr->id);

        expect(boardRowOf($this, 'D4')['state']['value'])->toBe('missing')
            ->and(boardRowOf($this, 'D4')['detail'])->toBe('Está publicada, pero no está a la vista de las personas: publique la página o registre un medio físico que la cubra.');
    });

    it('is met when the text is published for the center and the page is active or a means covered it', function (string $how): void {
        resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures)->id, [$this->north->id], $this->hr->id);
        $how === 'page'
            ? resolve(IssuePageLink::class)->handle($this->hr->id)
            : DisseminationRecord::factory()->covering(['b'])->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);

        expect(boardRowOf($this, 'D4'))->toMatchArray(['numeral' => '5.7 b', 'detail' => null])
            ->and(boardRowOf($this, 'D4')['state']['value'])->toBe('met');
    })->with(['page', 'means']);

    it('does not count a text published for another center, or a means that covers another inciso', function (): void {
        resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures)->id, [$this->south->id], $this->hr->id);
        DisseminationRecord::factory()->covering(['c'])->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);

        expect(boardRowOf($this, 'D4')['detail'])->toStartWith('Aún no hay información publicada');
    });
});

it('shows the people each block with the text published for their center, and says what is still missing', function (): void {
    $token = (string) resolve(IssuePageLink::class)->handle($this->hr->id)->token();
    resolve(PublishContent::class)->handle(contentDraft(DocumentKind::ViolenceMeasures, "Primer párrafo.\n\nSegundo párrafo.")->id, [$this->north->id], $this->hr->id);
    resolve(PublishContent::class)->handle(contentDraft(DocumentKind::HealthEffects, 'Salud.')->id, null, $this->hr->id);

    $this->get(route('workers.center', [$token, $this->north->id]))->assertInertia(fn (Assert $page) => $page
        ->where('blocks.b', ['text' => "Primer párrafo.\n\nSegundo párrafo.", 'publishedOn' => '20 nov 2026'])
        ->where('blocks.c', null)
        ->where('blocks.d', null)
        ->where('blocks.f', ['text' => 'Salud.', 'publishedOn' => '20 nov 2026']));
    $this->get(route('workers.center', [$token, $this->south->id]))->assertInertia(fn (Assert $page) => $page->where('blocks.b', null)->where('blocks.f.text', 'Salud.'));
});
