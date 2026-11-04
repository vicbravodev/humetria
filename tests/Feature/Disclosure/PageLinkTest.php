<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Disclosure\Actions\ChangePageLinkExpiry;
use App\Modules\Disclosure\Actions\DisablePageLink;
use App\Modules\Disclosure\Actions\EnablePageLink;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\RotatePageLink;
use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(IssuePageLink::class, RotatePageLink::class, DisablePageLink::class, EnablePageLink::class, ChangePageLinkExpiry::class, PageLink::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
});

it('issues one link per company, the same each time, and stores only its hash', function (): void {
    $first = resolve(IssuePageLink::class)->handle($this->hr->id);
    $again = resolve(IssuePageLink::class)->handle($this->hr->id);
    $token = $first->token();

    expect($again->id)->toBe($first->id)
        ->and(PageLink::query()->count())->toBe(1)
        ->and($first->version)->toBe(1)
        ->and($token)->toHaveLength(43)->toMatch('/^[A-Za-z0-9_-]{43}$/')
        ->and($first->token_hash)->toBe(hash('sha256', (string) $token))
        ->and($first->getAttributes())->not->toContain($token)
        ->and($first->status())->toBe(PageLinkStatus::Active)
        ->and($again->token())->toBe($token);
});

it('derives the token from the company, the version and the app key', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $other = Tenant::factory()->create();

    expect($link->token())->toBe(PageLink::tokenFor($this->tenant->id, 1, config()->string('app.key')))
        ->and(PageLink::tokenFor($other->id, 1, config()->string('app.key')))->not->toBe($link->token())
        ->and(PageLink::tokenFor($this->tenant->id, 2, config()->string('app.key')))->not->toBe($link->token())
        ->and(PageLink::tokenFor($this->tenant->id, 1, 'another key'))->not->toBe($link->token());
});

it('is still valid with a key in APP_PREVIOUS_KEYS, and not once the key is dropped', function (): void {
    config()->set('app.previous_keys', ['base64:'.base64_encode(str_repeat('p', 32))]);
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $old = PageLink::tokenFor($this->tenant->id, 1, 'base64:'.base64_encode(str_repeat('p', 32)));
    $link->update(['token_hash' => hash('sha256', $old)]);

    expect($link->fresh()?->token())->toBe($old);

    config()->set('app.previous_keys', []);

    expect($link->fresh()?->token())->toBeNull();
});

it('rotates the link: the old token stops deriving and the new one does', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $old = $link->token();

    $rotated = resolve(RotatePageLink::class)->handle($this->hr->id);

    expect($rotated->version)->toBe(2)
        ->and($rotated->token())->not->toBe($old)
        ->and($rotated->token())->toBe(PageLink::tokenFor($this->tenant->id, 2, config()->string('app.key')))
        ->and($rotated->token_hash)->not->toBe(hash('sha256', (string) $old))
        ->and($rotated->rotated_at?->toIso8601String())->toBe('2026-11-20T18:00:00+00:00');
});

it('issues the link on the first rotation when there was none', function (): void {
    expect(resolve(RotatePageLink::class)->handle($this->hr->id)->version)->toBe(1);
});

it('disables and enables the link without changing the poster', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $token = $link->token();

    $off = resolve(DisablePageLink::class)->handle($this->hr->id);
    expect($off->status())->toBe(PageLinkStatus::Disabled)->and($off->token())->toBe($token);

    $on = resolve(EnablePageLink::class)->handle($this->hr->id);
    expect($on->status())->toBe(PageLinkStatus::Active)->and($on->token())->toBe($token)->and($on->disabled_at)->toBeNull();
});

it('expires exactly at its moment: a second before it works, at that moment it no longer does', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $link->update(['expires_at' => '2026-11-20 18:00:01']);

    expect($link->fresh()?->status())->toBe(PageLinkStatus::Active);

    $this->travelTo('2026-11-20 18:00:01');
    expect($link->fresh()?->status())->toBe(PageLinkStatus::Expired);
});

it('sets the expiry to the end of a day in the display time zone, between tomorrow and 36 months', function (string $day, bool $accepted): void {
    resolve(IssuePageLink::class)->handle($this->hr->id);
    $change = fn () => resolve(ChangePageLinkExpiry::class)->handle($this->hr->id, $day);

    if ($accepted) {
        expect($change()->expires_at?->setTimezone('America/Mexico_City')->format('Y-m-d H:i:s'))->toBe("{$day} 23:59:59");
    } else {
        expect($change)->toThrow(ValidationException::class);
        expect(PageLink::query()->sole()->expires_at)->toBeNull();
    }
})->with([
    'today' => ['2026-11-20', false],
    'yesterday' => ['2026-11-19', false],
    'tomorrow' => ['2026-11-21', true],
    'in 36 months' => ['2029-11-20', true],
    'in 36 months and a day' => ['2029-11-21', false],
]);

it('takes the expiry away again', function (): void {
    resolve(IssuePageLink::class)->handle($this->hr->id);
    resolve(ChangePageLinkExpiry::class)->handle($this->hr->id, '2027-01-01');

    expect(resolve(ChangePageLinkExpiry::class)->handle($this->hr->id, null)->expires_at)->toBeNull();
});

it('sets the expiry on a link that does not exist yet by issuing it', function (): void {
    expect(resolve(ChangePageLinkExpiry::class)->handle($this->hr->id, '2027-01-01')->version)->toBe(1);
});

it('audits each change without the token, and logs ids and states only', function (): void {
    Log::spy();
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    resolve(RotatePageLink::class)->handle($this->hr->id);
    resolve(DisablePageLink::class)->handle($this->hr->id);
    resolve(EnablePageLink::class)->handle($this->hr->id);
    resolve(ChangePageLinkExpiry::class)->handle($this->hr->id, '2027-01-01');

    $events = AuditEntry::query()->where('event', 'like', 'disclosure.page_link_%')->orderBy('created_at')->pluck('event')->all();
    $metadata = json_encode(AuditEntry::query()->where('event', 'like', 'disclosure.page_link_%')->pluck('metadata')->all());
    expect($events)->toEqualCanonicalizing(['disclosure.page_link_issued', 'disclosure.page_link_rotated', 'disclosure.page_link_disabled', 'disclosure.page_link_enabled', 'disclosure.page_link_expiry_changed'])
        ->and($metadata)->not->toContain((string) $link->token())->not->toContain((string) PageLink::query()->sole()->token());
    foreach (['issued', 'rotated', 'disabled', 'enabled', 'expiry_changed'] as $event) {
        Log::shouldHaveReceived('info')->with("disclosure.page_link_{$event}", Mockery::on(fn (array $context): bool => ! str_contains(json_encode($context) ?: '', (string) $link->token())))->once();
    }
});

it('is one row per company and its expiry is after its creation', function (): void {
    resolve(IssuePageLink::class)->handle($this->hr->id);
    $try = fn (Closure $statement, string $constraint) => expect(fn () => DB::transaction($statement))->toThrow(QueryException::class, $constraint);

    $try(fn () => PageLink::factory()->create(['tenant_id' => $this->tenant->id]), 'disclosure_page_links_tenant_id_unique');
    $try(fn () => PageLink::query()->update(['expires_at' => now()->subDay()]), 'disclosure_page_links_expiry_after_creation');
});

it('scopes the link to the current company', function (): void {
    $other = Tenant::factory()->create();
    PageLink::factory()->create(['tenant_id' => $other->id]);
    $own = resolve(IssuePageLink::class)->handle($this->hr->id);

    expect(PageLink::query()->pluck('id')->all())->toBe([$own->id]);
});
