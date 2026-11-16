<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\OpenChannel;
use App\Modules\Complaints\Actions\SubmitComplaint;
use App\Modules\Complaints\Enums\ComplaintCategory;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\ComplaintInput;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Support\DemoMail;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\Stories\ComplaintsStory;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    // In production the jobs run later on the queue: the story must stand without them.
    Bus::fake();
});

it('tells the complaints story: an active channel with a stable code, handlers, anonymous cases in several states', function (): void {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.' '.json_encode($log->context);
    });

    resolve(DemoWorkspace::class)->prepare();

    $tenantId = resolve(DemoCompanies::class)->id();
    $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
    $channel = ComplaintChannel::query()->sole();

    // has an active public channel with a stable access code
    $code = ComplaintsStory::accessCode();
    expect($channel->access_code)->toBe($code)
        ->and(ComplaintsStory::accessCode())->toBe($code)
        ->and($channel->isOpen())->toBeTrue()
        ->and($channel->paused_at)->toBeNull()
        ->and($channel->published_on?->lessThan($today))->toBeTrue()
        ->and(resolve(OpenChannel::class)->handle($code)->channel->id)->toBe($channel->id);
    // derived from the application's key, so nobody without it can guess the demo's link
    $key = config('app.key');
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    expect(ComplaintsStory::accessCode())->not->toBe($code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{16}$/');
    config(['app.key' => $key]);
    $this->get(route('complaints.public.show', $code))->assertOk();

    // has handlers designated: the owner and HR, for the whole company, with addresses of the demo's domain
    $handlers = ComplaintHandler::query()->whereNull('ended_at')->get();
    $handlerUsers = App\Models\User::query()->whereIn('id', $handlers->pluck('user_id'))->get();
    expect($handlers)->toHaveCount(2)
        ->and($handlers->pluck('work_center_id')->filter()->all())->toBe([])
        ->and($handlerUsers->pluck('email')->every(fn (string $email): bool => str_ends_with($email, '@'.DemoMail::DOMAIN)))->toBeTrue();

    // has cases in several states: received, acknowledged, in review and closed with an outcome
    $complaints = Complaint::query()->get();
    $new = $complaints->where('status', ComplaintStatus::New);
    expect($new->whereNull('acknowledged_at'))->not->toBeEmpty()
        ->and($new->whereNotNull('acknowledged_at'))->not->toBeEmpty()
        ->and($complaints->where('status', ComplaintStatus::InReview)->count())->toBeGreaterThanOrEqual(2)
        ->and($complaints->where('status', ComplaintStatus::Closed)->count())->toBeGreaterThanOrEqual(2)
        ->and($complaints->where('status', ComplaintStatus::Closed)->every(fn (Complaint $case): bool => $case->resolution !== null && $case->closed_on !== null))->toBeTrue()
        ->and($complaints->every(fn (Complaint $case): bool => $case->received_on->lessThan($today) && $case->channel_id === $channel->id))->toBeTrue()
        ->and($complaints->every(fn (Complaint $case): bool => $case->purged_on === null && ! $case->on_hold))->toBeTrue();
    // every case reached both handlers when it came in
    foreach ($complaints as $case) {
        expect(ComplaintAssignment::query()->where('complaint_id', $case->id)->whereNull('ended_on')->pluck('user_id')->sort()->values()->all())
            ->toBe($handlers->pluck('user_id')->sort()->values()->all());
    }
    // a closed case stays far from the purge: the channel keeps cases two years
    expect($complaints->where('status', ComplaintStatus::Closed)->every(fn (Complaint $case): bool => $case->closed_on?->greaterThan($today->subMonths($channel->retention_months)) === true))->toBeTrue();
    // one case has a follow-up: the person answered a question of the handler, and was answered back
    $followed = $complaints->first(fn (Complaint $case): bool => ComplaintEntry::query()->where('complaint_id', $case->id)->where('kind', EntryKind::ReporterMessage->value)->exists());
    expect($followed)->not->toBeNull();
    $thread = ComplaintEntry::query()->where('complaint_id', $followed?->id)->orderBy('position')->pluck('kind')->all();
    expect(array_search(EntryKind::ReporterMessage->value, $thread, true))->toBeLessThan(count($thread) - 1)
        ->and(end($thread))->toBe(EntryKind::HandlerMessage->value);
    // a live visitor has the whole daily cap: nothing seeded is dated today
    expect($complaints->filter(fn (Complaint $case): bool => $case->received_on->isSameDay($today))->count())->toBe(0);

    // keeps reporters anonymous: no row ties a complaint to a person, an invitation or an hour
    expect($complaints->every(fn (Complaint $case): bool => $case->identity_mode === IdentityMode::Anonymous
        && $case->reporter_name === null && $case->reporter_contact === null && $case->reporter_email === null))->toBeTrue()
        ->and(ComplaintEntry::query()->where('kind', EntryKind::ReporterMessage->value)->get()->every(fn (ComplaintEntry $entry): bool => $entry->author_id === null && $entry->occurred_at === null))->toBeTrue()
        ->and($complaints->pluck('id')->every(fn (string $id): bool => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) === 1))->toBeTrue();
    $references = collect(DB::select(<<<'SQL'
        SELECT DISTINCT target.relname AS referenced FROM pg_constraint c
        JOIN pg_class source ON source.oid = c.conrelid JOIN pg_class target ON target.oid = c.confrelid
        WHERE source.relname = 'complaints' AND c.contype = 'f' ORDER BY referenced
    SQL))->pluck('referenced')->all();
    expect($references)->toBe(['complaint_channels', 'tenants', 'work_centers']);
    // no case names a person of the roster
    $texts = $complaints->map(fn (Complaint $case): string => (string) $case->description)
        ->merge(ComplaintEntry::query()->whereNotNull('body')->get()->map(fn (ComplaintEntry $entry): string => (string) $entry->body))
        ->filter(fn (string $text): bool => $text !== '')->values();
    foreach (Database\Seeders\Demo\DemoRoster::people() as $person) {
        expect($texts->contains(fn (string $text): bool => str_contains($text, $person->name)))->toBeFalse();
    }

    // does not log or audit complaint text
    $audits = AuditEntry::query()->where('tenant_id', $tenantId)->get()->map(fn (AuditEntry $entry): string => $entry->event.' '.json_encode($entry->metadata))->implode("\n");
    $logs = implode("\n", $logged);
    expect($texts)->not->toBeEmpty();
    foreach ($texts as $text) {
        expect(str_contains($logs, $text))->toBeFalse()->and(str_contains($audits, $text))->toBeFalse();
    }

    // nothing outside the demo company
    foreach ([ComplaintChannel::class, ComplaintHandler::class, Complaint::class, ComplaintAssignment::class, ComplaintEntry::class] as $model) {
        expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
    }

    // A case of the story has the shape of one a visitor files through SubmitComplaint: the same columns, the same assignments.
    $story = $new->whereNull('acknowledged_at')->firstOrFail();
    $receipt = resolve(SubmitComplaint::class)->handle($channel, new ComplaintInput(
        ComplaintType::UnfavorablePractice, ComplaintCategory::WorkloadOrHours, 'Un visitante de la demo escribe su queja en vivo desde el cartel.',
        null, $story->work_center_id, RespondentKind::Unknown, null, IdentityMode::Anonymous,
    ));
    $live = Complaint::query()->where('reference', $receipt->reference)->sole();
    $filled = fn (Complaint $case): array => array_keys(array_filter($case->getAttributes(), fn (mixed $value): bool => $value !== null));
    $assigned = fn (Complaint $case): array => ComplaintAssignment::query()->where('complaint_id', $case->id)->get()
        ->map(fn (ComplaintAssignment $row): array => [array_keys(array_filter($row->getAttributes(), fn (mixed $value): bool => $value !== null)), $row->user_id])->sortBy(1)->values()->all();
    expect($filled($story))->toEqualCanonicalizing($filled($live))
        ->and(strlen($story->reference))->toBe(strlen($live->reference))
        ->and(strlen($story->access_key_hash))->toBe(strlen($live->access_key_hash))
        ->and(ComplaintEntry::query()->where('complaint_id', $story->id)->count())->toBe(ComplaintEntry::query()->where('complaint_id', $live->id)->count())
        ->and($assigned($story))->toEqual($assigned($live));
});
