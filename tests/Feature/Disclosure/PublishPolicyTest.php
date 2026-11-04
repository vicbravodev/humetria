<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Disclosure\Actions\PublishPolicy;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Events\PolicyPublished;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(PublishPolicy::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create();
    $this->south = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
});

/** A complete draft: responsibles and resources written. */
function completeDraft(string $responsibles = 'La dirección de RH.', string $resources = 'Presupuesto anual.'): Document
{
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    $sections = array_map(fn (array $section): array => [
        'key' => $section['key'],
        'text' => match ($section['key']) {
            'responsibles' => $responsibles,
            'resources' => $resources,
            default => $section['text'],
        },
        'items' => $section['items'],
    ], $draft->content);

    return resolve(SaveDraft::class)->handle($draft->id, $sections);
}

/** @param list<string>|null $centers @param list<string> $covers */
function publishPolicy(Document $draft, ?array $centers, array $covers = ['a', 'b', 'c'], bool $reviewed = true): Document
{
    return resolve(PublishPolicy::class)->handle($draft->id, $centers, $covers, $reviewed, test()->hr->id);
}

it('publishes the draft as version 1 for the chosen centers', function (): void {
    $draft = completeDraft();

    $published = publishPolicy($draft, [$this->north->id]);

    expect($published->id)->toBe($draft->id)
        ->and($published->status)->toBe(DocumentStatus::Published)
        ->and($published->version)->toBe(1)
        ->and($published->covers)->toBe(['a', 'b', 'c'])
        ->and($published->published_by)->toBe($this->hr->id)
        ->and($published->published_at)->not->toBeNull()
        ->and(DocumentCenter::query()->pluck('work_center_id')->all())->toBe([$this->north->id]);
});

it('numbers the next version from the latest one', function (): void {
    publishPolicy(completeDraft(), null);
    $second = publishPolicy(completeDraft(), [$this->south->id]);
    Document::factory()->published()->create(['tenant_id' => $this->tenant->id, 'version' => 7, 'kind' => DocumentKind::HealthEffects, 'covers' => null]);

    expect($second->version)->toBe(2)
        ->and(publishPolicy(completeDraft(), null)->version)->toBe(3);
});

it('numbers each company on its own', function (): void {
    $other = Tenant::factory()->create();
    Document::factory()->published()->policy()->create(['tenant_id' => $other->id, 'version' => 4]);

    expect(publishPolicy(completeDraft(), null)->version)->toBe(1);
});

it('takes the lock of the company before picking the number', function (): void {
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    publishPolicy(completeDraft(), null);

    expect(array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'pg_advisory_xact_lock'))))->toHaveCount(1);
});

it('publishes to every center in use when none is named, and never to an archived one', function (): void {
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();

    $published = publishPolicy(completeDraft(), null);
    $centers = DocumentCenter::query()->where('document_id', $published->id)->pluck('work_center_id')->all();

    expect($centers)->toHaveCount(2)->toContain($this->north->id, $this->south->id)->not->toContain($archived->id);
});

it('publishes with some of a, b and c unmarked: the board warns, the norm does not block', function (): void {
    expect(publishPolicy(completeDraft(), null, ['b'])->covers)->toBe(['b'])
        ->and(publishPolicy(completeDraft(), null, [])->covers)->toBe([]);
});

it('announces the publication once it is committed, with the centers it reached', function (): void {
    Event::fake([PolicyPublished::class]);

    $published = publishPolicy(completeDraft(), [$this->south->id, $this->north->id]);

    Event::assertDispatched(PolicyPublished::class, fn (PolicyPublished $event): bool => $event->tenantId === $this->tenant->id
        && $event->documentId === $published->id
        && $event->workCenterIds === collect([$this->north->id, $this->south->id])->sort()->values()->all());
});

it('writes the audit trail and the log without the words of the policy', function (): void {
    Log::spy();
    $published = publishPolicy(completeDraft('Texto secreto de RH'), [$this->north->id]);

    $entry = AuditEntry::query()->where('event', 'disclosure.published')->sole();
    expect($entry->subject_id)->toBe($published->id)
        ->and($entry->metadata)->toEqual(['document_id' => $published->id, 'kind' => 'policy', 'version' => 1, 'covers' => ['a', 'b', 'c'], 'work_center_ids' => [$this->north->id]])
        ->and(json_encode($entry->metadata))->not->toContain('secreto');
    Log::shouldHaveReceived('info')->with('disclosure.published', ['document_id' => $published->id, 'kind' => 'policy', 'version' => 1, 'work_center_count' => 1])->once();
});

it('refuses a publication that is not valid and changes nothing', function (Closure $attempt, string $field): void {
    $draft = completeDraft();
    Event::fake([PolicyPublished::class]);

    expect(fn () => $attempt($draft, $this))->toThrow(ValidationException::class);

    try {
        $attempt($draft, $this);
    } catch (ValidationException $failure) {
        expect(array_keys($failure->errors()))->toContain($field);
    }

    expect($draft->fresh()?->status)->toBe(DocumentStatus::Draft)
        ->and(DocumentCenter::query()->count())->toBe(0);
    Event::assertNotDispatched(PolicyPublished::class);
})->with([
    'no centers' => [fn (Document $draft, $test) => publishPolicy($draft, []), 'work_center_ids'],
    'a center that does not exist' => [fn (Document $draft, $test) => publishPolicy($draft, ['0194c1de-0000-7000-8000-000000000000']), 'work_center_ids'],
    'a center that is not a uuid' => [fn (Document $draft, $test) => publishPolicy($draft, ['x']), 'work_center_ids'],
    'a center of another company' => [fn (Document $draft, $test) => publishPolicy($draft, [WorkCenter::factory()->for(Tenant::factory()->create())->create()->id]), 'work_center_ids'],
    'an archived center' => [fn (Document $draft, $test) => publishPolicy($draft, [WorkCenter::factory()->for($test->tenant)->archived()->create()->id]), 'work_center_ids'],
    'an unknown inciso' => [fn (Document $draft, $test) => publishPolicy($draft, null, ['a', 'z']), 'covers'],
    'without the review' => [fn (Document $draft, $test) => publishPolicy($draft, null, reviewed: false), 'reviewed'],
]);

it('refuses a policy without responsibles or without resources, each on its field', function (string $responsibles, string $resources, string $field): void {
    $draft = completeDraft($responsibles, $resources);

    try {
        publishPolicy($draft, null);
        $this->fail('The publication should have been refused.');
    } catch (ValidationException $failure) {
        expect(array_keys($failure->errors()))->toBe([$field]);
    }
})->with([
    'no responsibles' => ['', 'Presupuesto', 'responsibles'],
    'blank responsibles' => ["  \n ", 'Presupuesto', 'responsibles'],
    'no resources' => ['La dirección', '', 'resources'],
]);

it('says both fields when both are missing', function (): void {
    $draft = completeDraft('', '');

    try {
        publishPolicy($draft, null);
        $this->fail('The publication should have been refused.');
    } catch (ValidationException $failure) {
        expect(array_keys($failure->errors()))->toBe(['responsibles', 'resources']);
    }
});

it('does not publish what is already published, another company\'s draft or one that does not exist', function (): void {
    $published = publishPolicy(completeDraft(), null);
    $foreign = Document::factory()->draft()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);

    foreach ([$published->id, $foreign->id, '0194c1de-0000-7000-8000-000000000000'] as $id) {
        expect(fn () => resolve(PublishPolicy::class)->handle($id, null, ['a', 'b', 'c'], true, $this->hr->id))->toThrow(ModelNotFoundException::class);
    }
});

it('never lets a published version or its centers change', function (): void {
    $published = publishPolicy(completeDraft(), [$this->north->id]);
    $reach = DocumentCenter::query()->sole();
    $try = fn (Closure $statement) => expect(fn () => DB::transaction($statement))->toThrow(QueryException::class, 'final');

    $try(fn () => DB::table('disclosure_documents')->where('id', $published->id)->update(['content' => '[]']));
    $try(fn () => DB::table('disclosure_documents')->where('id', $published->id)->delete());
    $try(fn () => DB::table('disclosure_document_centers')->where('id', $reach->id)->update(['work_center_id' => $this->south->id]));
    $try(fn () => DB::table('disclosure_document_centers')->where('id', $reach->id)->delete());

    expect(Document::query()->find($published->id)?->version)->toBe(1);
});

it('still lets a draft be edited and thrown away', function (): void {
    $draft = completeDraft();

    DB::table('disclosure_documents')->where('id', $draft->id)->update(['updated_at' => now()]);
    DB::table('disclosure_documents')->where('id', $draft->id)->delete();

    expect(Document::query()->count())->toBe(0);
});
