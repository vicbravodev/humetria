<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The integrity of the policy and its disclosure lives in the database too (CLAUDE.md): each rule a column can hold is a
 * check, tried here with direct inserts.
 */
/**
 * Inserts a row as the factory builds it, with `$attributes` on top, straight into the table: the model's casts would refuse an
 * unknown value before the database could.
 *
 * @param  Factory<Illuminate\Database\Eloquent\Model>  $factory
 * @param  array<string, mixed>  $attributes
 */
function insertDisclosure(Factory $factory, array $attributes): void
{
    $row = array_map(fn (mixed $value): mixed => match (true) {
        $value instanceof BackedEnum => $value->value,
        is_array($value) => json_encode($value),
        default => $value,
    }, $factory->raw($attributes));

    DB::table($factory->newModel()->getTable())->insert([...$row, 'id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
}

/** Runs a statement that must fail, in a savepoint, so the transaction of the test goes on. */
function refusedByTheDatabase(Closure $statement, string $constraint): void
{
    expect(fn () => DB::transaction($statement))->toThrow(QueryException::class, $constraint);
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->in = ['tenant_id' => $this->tenant->id];
    workIn($this->tenant);
});

it('stores a draft and a published version of the same kind', function (): void {
    Document::factory()->draft()->create($this->in);
    $published = Document::factory()->published()->create($this->in);

    expect(Document::query()->count())->toBe(2)
        ->and($published->version)->toBe(1)
        ->and($published->covers)->toBe(['a', 'b', 'c']);
});

it('keeps one draft per company and kind', function (): void {
    Document::factory()->draft()->create($this->in);

    refusedByTheDatabase(fn () => Document::factory()->draft()->create($this->in), 'disclosure_documents_one_draft');

    Document::factory()->draft()->create(['tenant_id' => Tenant::factory()->create()->id]);
    expect(Document::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('numbers the versions of a kind once per company', function (): void {
    Document::factory()->published()->create([...$this->in, 'version' => 1]);

    refusedByTheDatabase(fn () => Document::factory()->published()->create([...$this->in, 'version' => 1]), 'disclosure_documents_version_unique');

    Document::factory()->published()->create([...$this->in, 'version' => 2]);
    Document::factory()->published()->create(['tenant_id' => Tenant::factory()->create()->id, 'version' => 1]);
    expect(Document::query()->withoutGlobalScopes()->count())->toBe(3);
});

it('refuses what the checks refuse', function (array $attributes, string $constraint): void {
    refusedByTheDatabase(fn () => insertDisclosure(Document::factory()->draft(), [...$this->in, ...$attributes]), $constraint);
})->with([
    'an unknown kind' => [['kind' => 'rules'], 'disclosure_documents_kind_known'],
    // The row breaks two checks and the database names the first it meets.
    'an unknown status' => [['status' => 'archived'], 'violates check constraint'],
    'version zero' => [['version' => 0, 'status' => 'published', 'published_at' => '2026-11-03', 'published_by' => 1], 'disclosure_documents_version_positive'],
    'content that is not a list' => [['content' => ['a' => 1]], 'disclosure_documents_content_array'],
    'covers on a block that is not the policy' => [['kind' => 'health_effects', 'covers' => ['a']], 'disclosure_documents_covers_of_policy'],
    'covers that is not a list' => [['covers' => ['a' => 1]], 'disclosure_documents_covers_of_policy'],
    'a draft that has a number' => [['version' => 1], 'disclosure_documents_published_is_complete'],
    'a draft that has a publication day' => [['published_at' => '2026-11-03'], 'disclosure_documents_published_is_complete'],
]);

it('refuses a published version that is incomplete', function (array $missing): void {
    $user = User::factory()->create();
    $complete = ['status' => 'published', 'version' => 1, 'published_at' => now(), 'published_by' => $user->id];

    refusedByTheDatabase(fn () => insertDisclosure(Document::factory(), [...$this->in, ...array_merge($complete, $missing)]), 'disclosure_documents_published_is_complete');
})->with([
    'no number' => [['version' => null]],
    'no day' => [['published_at' => null]],
    'no author' => [['published_by' => null]],
]);

it('scopes the documents to the current company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Document::factory()->published()->create(['tenant_id' => $other->id]);
    $own = Document::factory()->published()->create($this->in);

    expect(Document::query()->pluck('id')->all())->toBe([$own->id])
        ->and(Document::query()->find($foreign->id))->toBeNull();
});
