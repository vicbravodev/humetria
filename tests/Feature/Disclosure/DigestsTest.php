<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Actions\SendDisclosureDigests;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Notifications\DisclosureDigest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

mutates(SendDisclosureDigests::class, DisclosureDigest::class);

beforeEach(function (): void {
    // Monday, 9:45 in Mexico City.
    $this->travelTo('2026-11-02 15:45 UTC');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = ownerOf($this->tenant);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    workIn($this->tenant);
    // The company has started with the disclosure: a link to its page exists.
    resolve(IssuePageLink::class)->handle($this->owner->id);
});

/** @param list<WorkCenter> $centers */
function digestPolicy(object $test, array $centers): void
{
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $test->tenant->id, 'published_by' => $test->owner->id]);

    foreach ($centers as $center) {
        DocumentCenter::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $center->id]);
    }
}

/** @return list<string> the lines of the email to the owner, after the opening one */
function digestLines(): array
{
    $lines = null;
    Notification::assertSentOnDemand(DisclosureDigest::class, function (DisclosureDigest $digest, array $channels, AnonymousNotifiable $notifiable) use (&$lines): bool {
        $lines = array_slice($digest->toMail($notifiable)->introLines, 1);

        return true;
    });

    return $lines ?? throw new RuntimeException('No digest.');
}

it('tells the owner, with counts only, what waits: centers without policy, closed evaluations not published, a link about to expire', function (): void {
    digestPolicy($this, [$this->north]);
    staffOf($this->tenant, $this->south, 20);
    [$campaign] = closedEvaluationInAreas($this->tenant, ['Área 1' => 12]);
    $campaign->update(['work_center_id' => $this->south->id]);
    App\Modules\Disclosure\Models\PageLink::query()->update(['expires_at' => now()->addDays(10)]);

    $sent = resolve(SendDisclosureDigests::class)->handle();

    expect($sent)->toBe(1)
        // The evaluation brought a center of its own, which has no policy either.
        ->and(digestLines())->toBe(['2 centros sin política publicada.', '1 centro con resultados sin publicar a las personas.', 'El enlace de la página vence en 10 días.']);
});

it('says «los resultados» only of centers whose latest closed evaluation is not published', function (): void {
    staffOf($this->tenant, $this->south, 20);
    [$campaign] = closedEvaluationInAreas($this->tenant, ['Área 1' => 12]);
    $campaign->update(['work_center_id' => $this->south->id]);
    resolve(PublishResults::class)->handle($campaign->id, $this->owner->id);

    resolve(SendDisclosureDigests::class)->handle();

    expect(implode(' ', digestLines()))->not->toContain('resultados');
});

it('sends nothing when everything is in order, or when the company never started with the disclosure', function (): void {
    digestPolicy($this, [$this->north, $this->south]);
    expect(resolve(SendDisclosureDigests::class)->handle())->toBe(0);

    $quiet = Tenant::factory()->create();
    WorkCenter::factory()->for($quiet)->create();
    ownerOf($quiet);

    expect(resolve(SendDisclosureDigests::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

it('does not tell a blocked company, one without an owner, and does not let one that fails stop the others', function (): void {
    Exceptions::fake();
    $blocked = Tenant::factory()->create();
    workIn($blocked);
    WorkCenter::factory()->for($blocked)->create();
    resolve(IssuePageLink::class)->handle(ownerOf($blocked)->id);
    Tenant::query()->whereKey($blocked->id)->update(['deactivated_at' => now()]);
    $ownerless = Tenant::factory()->create();
    WorkCenter::factory()->for($ownerless)->create();
    DB::table('disclosure_page_links')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $ownerless->id, 'version' => 1, 'token_hash' => hash('sha256', 'x'), 'enabled_at' => now(), 'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now()]);
    workIn($this->tenant);

    $sent = resolve(SendDisclosureDigests::class)->handle();

    expect($sent)->toBe(1);
    Notification::assertSentOnDemandTimes(DisclosureDigest::class, 1);
});

it('names no center, no title and no text, speaks to the owner as «tú» and links to the board', function (): void {
    resolve(SendDisclosureDigests::class)->handle();

    Notification::assertSentOnDemand(DisclosureDigest::class, function (DisclosureDigest $digest, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $digest->toMail($notifiable);
        $text = implode(' ', [$mail->subject, ...$mail->introLines]);

        return $notifiable->routes['mail'] === $this->owner->email
            && $mail->actionUrl === route('tenant.disclosure.index', 'acme')
            && ! str_contains($text, 'Planta Norte')
            && str_contains($mail->subject, 'tu política');
    });
});

it('logs how many it sent', function (): void {
    Log::spy();

    resolve(SendDisclosureDigests::class)->handle();

    Log::shouldHaveReceived('info')->with('disclosure.digests_sent', ['sent' => 1])->once();
});

it('runs from the command and from the Monday schedule, at 09:45 display time, once and without overlapping', function (): void {
    $this->artisan('disclosure:send-digests')->expectsOutput('Resúmenes enviados: 1')->assertSuccessful();

    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($event): bool => str_contains($event->command ?? '', 'disclosure:'));
    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('45 9 * * 1')
        ->and($events->first()->timezone)->toBe('America/Mexico_City')
        ->and($events->first()->onOneServer && $events->first()->withoutOverlapping)->toBeTrue();
});

// Board V4-Correos-2: while people of the policy in force have not confirmed it, the Monday email says how many are missing,
// how many confirmed, and its one button reminds them from the record of the disclosure.
it('says how many people are missing to confirm the policy in force, and offers to remind them', function (): void {
    digestPolicy($this, [$this->north, $this->south]);
    $policy = Document::query()->sole();
    $people = staffOf($this->tenant, $this->north, 3);

    foreach ($people as $index => $person) {
        $ack = App\Modules\Disclosure\Models\Acknowledgment::factory();
        ($index === 0 ? $ack->portal() : $ack)->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $person->id]);
    }

    expect(resolve(SendDisclosureDigests::class)->handle())->toBe(1);

    Notification::assertSentOnDemand(DisclosureDigest::class, function (DisclosureDigest $digest, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $digest->toMail($notifiable);

        return $mail->subject === 'Faltan 2 personas por confirmar la política'
            && $mail->introLines === ['1 de 3 personas confirmaron que leyeron la política de prevención. Puedes mandarles un recordatorio o imprimir la lista para que firmen en papel.']
            && $mail->actionText === 'Recordar a 2'
            && $mail->actionUrl === route('tenant.disclosure.dissemination', 'acme')
            && str_contains((string) $mail->render(), 'Te lo enviamos cada lunes mientras falten confirmaciones.');
    });
});

it('says «Falta 1 persona» when only one is missing, with the rest of what waits after it', function (): void {
    digestPolicy($this, [$this->north]);
    $ana = staffOf($this->tenant, $this->north, 1)->sole();
    App\Modules\Disclosure\Models\Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => Document::query()->sole()->id, 'employee_id' => $ana->id]);

    resolve(SendDisclosureDigests::class)->handle();

    Notification::assertSentOnDemand(DisclosureDigest::class, fn (DisclosureDigest $digest, array $channels, AnonymousNotifiable $notifiable): bool => $digest->toMail($notifiable)->subject === 'Falta 1 persona por confirmar la política'
        && $digest->toMail($notifiable)->introLines === ['0 de 1 personas confirmaron que leyeron la política de prevención. Puedes mandarles un recordatorio o imprimir la lista para que firmen en papel.', '1 centro sin política publicada.']
        && $digest->toMail($notifiable)->actionText === 'Recordar a 1');
});
