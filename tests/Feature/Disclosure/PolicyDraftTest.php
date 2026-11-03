<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\DiscardDraft;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Instruments\Data\PolicySection;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(StartPolicyDraft::class, SaveDraft::class, DiscardDraft::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** The sections the form sends for a draft: the key, the text and the items of each. @return list<array{key: string, text: string, items: list<string>}> */
function draftForm(Document $draft, array $overrides = []): array
{
    return array_map(fn (array $section): array => ['key' => $section['key'], 'text' => $section['text'], 'items' => $section['items'], ...($overrides[$section['key']] ?? [])], $draft->content);
}

/** The characters of a content: its texts and its items. @param list<array{text: string, items: list<string>}> $content */
function draftSize(array $content): int
{
    return array_sum(array_map(fn (array $section): int => mb_strlen($section['text']) + array_sum(array_map(mb_strlen(...), $section['items'])), $content));
}

it('starts the draft from the template of the guide', function (): void {
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    $template = resolve(Nom035Policy::class)->template();

    expect($draft->kind)->toBe(DocumentKind::Policy)
        ->and($draft->status)->toBe(DocumentStatus::Draft)
        ->and($draft->version)->toBeNull()
        ->and($draft->template_version)->toBe('dof-2018-10-23')
        ->and(array_column($draft->content, 'key'))->toBe(array_map(fn (PolicySection $section): string => $section->key, $template->sections))
        ->and($draft->content[0])->toBe(['key' => 'commitments', 'heading' => 'Compromisos', 'text' => $template->sections[0]->text, 'items' => $template->sections[0]->items, 'list_style' => 'numbers'])
        ->and($draft->covers)->toBeNull();
});

it('starts one draft only: asking again gives the same one', function (): void {
    $first = resolve(StartPolicyDraft::class)->handle(null);
    $second = resolve(StartPolicyDraft::class)->handle(null);

    expect($second->id)->toBe($first->id)
        ->and(Document::query()->count())->toBe(1);
});

it('starts a new version from the latest published one', function (): void {
    $older = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 1]);
    $content = [...array_slice($older->content, 0, 1), ['key' => 'responsibles', 'heading' => 'Responsables', 'text' => 'Dirección de RH', 'items' => [], 'list_style' => null]];
    Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 2, 'content' => $content]);

    $draft = resolve(StartPolicyDraft::class)->handle(null);

    expect($older->version)->toBe(1)
        ->and(array_column($draft->content, 'text', 'key')['responsibles'])->toBe('Dirección de RH')
        ->and($draft->status)->toBe(DocumentStatus::Draft);
});

it('does not take a draft of another company', function (): void {
    $other = Tenant::factory()->create();
    Document::factory()->draft()->policy()->create(['tenant_id' => $other->id]);

    $draft = resolve(StartPolicyDraft::class)->handle(null);

    expect($draft->tenant_id)->toBe($this->tenant->id)
        ->and(Document::query()->count())->toBe(1);
});

it('saves what HR writes, section by section', function (): void {
    $draft = resolve(StartPolicyDraft::class)->handle(null);

    $saved = resolve(SaveDraft::class)->handle($draft->id, draftForm($draft, [
        'responsibles' => ['text' => "  Dirección de RH.\n\nCada gerente.  "],
        'resources' => ['text' => 'Presupuesto anual de capacitación.'],
        'commitments' => ['items' => ['  Aplicar esta política.  ', '', 'Atender los casos.']],
    ]));
    $byKey = array_column($saved->content, null, 'key');

    expect($byKey['responsibles']['text'])->toBe("Dirección de RH.\n\nCada gerente.")
        ->and($byKey['resources']['text'])->toBe('Presupuesto anual de capacitación.')
        ->and($byKey['commitments']['items'])->toBe(['Aplicar esta política.', 'Atender los casos.'])
        ->and($byKey['commitments']['heading'])->toBe('Compromisos')
        ->and($byKey['commitments']['list_style'])->toBe('numbers');
});

it('lets a principle go but not the sections the guide requires', function (): void {
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    $without = fn (string $key): array => array_values(array_filter(draftForm($draft), fn (array $section): bool => $section['key'] !== $key));

    $saved = resolve(SaveDraft::class)->handle($draft->id, $without('principle_7'));

    expect(array_column($saved->content, 'key'))->not->toContain('principle_7')->toHaveCount(11);

    foreach (['commitments', 'responsibles', 'resources'] as $required) {
        expect(fn () => resolve(SaveDraft::class)->handle($draft->id, $without($required)))
            ->toThrow(ValidationException::class);
    }
});

it('refuses a section the template does not have, and a repeated one', function (): void {
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    $form = draftForm($draft);

    expect(fn () => resolve(SaveDraft::class)->handle($draft->id, [...$form, ['key' => 'invented', 'text' => 'x', 'items' => []]]))->toThrow(ValidationException::class)
        ->and(fn () => resolve(SaveDraft::class)->handle($draft->id, [...$form, $form[3]]))->toThrow(ValidationException::class);
});

it('keeps the sections in the order of the template whatever order they come in', function (): void {
    $draft = resolve(StartPolicyDraft::class)->handle(null);

    $saved = resolve(SaveDraft::class)->handle($draft->id, array_reverse(draftForm($draft)));

    expect(array_column($saved->content, 'key')[0])->toBe('commitments');
});

it('refuses too many characters in all (20 000 allowed) and too many items', function (): void {
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    $room = 20000 - draftSize($draft->content);

    $atTheLimit = resolve(SaveDraft::class)->handle($draft->id, draftForm($draft, ['responsibles' => ['text' => str_repeat('a', $room)]]));
    expect(draftSize($atTheLimit->content))->toBe(20000);

    expect(fn () => resolve(SaveDraft::class)->handle($draft->id, draftForm($draft, ['responsibles' => ['text' => str_repeat('a', $room + 1)]])))->toThrow(ValidationException::class)
        ->and(fn () => resolve(SaveDraft::class)->handle($draft->id, draftForm($draft, ['commitments' => ['items' => array_fill(0, 61, 'x')]])))->toThrow(ValidationException::class);
});

it('does not save a version that is already published, one of another company or one that does not exist', function (): void {
    $published = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id]);
    $other = Document::factory()->draft()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);

    foreach ([$published->id, $other->id, '0194c1de-0000-7000-8000-000000000000'] as $id) {
        expect(fn () => resolve(SaveDraft::class)->handle($id, draftForm($published)))->toThrow(ModelNotFoundException::class);
    }

    expect($published->fresh()?->content)->toEqual($published->content);
});

it('discards the draft and nothing else', function (): void {
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    $published = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id]);

    resolve(DiscardDraft::class)->handle($draft->id);

    expect(Document::query()->pluck('id')->all())->toBe([$published->id])
        ->and(fn () => resolve(DiscardDraft::class)->handle($published->id))->toThrow(ModelNotFoundException::class)
        ->and(Document::query()->count())->toBe(1);
});

it('logs each step with ids only, never the text', function (): void {
    Log::spy();
    $draft = resolve(StartPolicyDraft::class)->handle(memberOf($this->tenant, TenantRole::Hr)->id);
    resolve(SaveDraft::class)->handle($draft->id, draftForm($draft, ['responsibles' => ['text' => 'Texto secreto de RH']]));
    resolve(DiscardDraft::class)->handle($draft->id);

    Log::shouldHaveReceived('info')->with('disclosure.draft_started', ['document_id' => $draft->id, 'kind' => 'policy'])->once();
    Log::shouldHaveReceived('info')->with('disclosure.draft_saved', ['document_id' => $draft->id, 'kind' => 'policy'])->once();
    Log::shouldHaveReceived('info')->with('disclosure.draft_discarded', ['document_id' => $draft->id, 'kind' => 'policy'])->once();
});
