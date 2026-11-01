<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Http\Controllers\PortalChecklistController;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Support\PhotoStore;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalChecklistController::class, PhotoStore::class);

beforeEach(function (): void {
    Storage::fake('telework_files');
    config()->set('filesystems.telework_files', 'telework_files');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id]);
    $this->checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $teleworker->work_center_id]);
    $this->yes = addQuestion($this->checklist, 1, 'yes');
    $this->health = addQuestion($this->checklist, 2, 'yes', sensitive: true);
    $this->application = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'checklist_id' => $this->checklist->id]);
    $this->token = resolve(IssuePortalLink::class)->handle($this->employee->id);
    $this->url = route('portal.checklist.show', [$this->token, $this->application->id]);
});

/** @return array<string, mixed> */
function form(array $overrides = []): array
{
    return ['consent' => '1', 'answers' => [test()->yes->id => 'no', test()->health->id => 'yes'], 'risk_notes' => [test()->yes->id => 'Cable suelto'], ...$overrides];
}

it('lives at /portal/{token}/checklists/{application}', function (): void {
    expect(route('portal.checklist.show', ['abc', 'def'], absolute: false))->toBe('/portal/abc/checklists/def');
});

it('shows the questions with the instructions, the notice and the consent, and no answers', function (): void {
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page
        ->component('telework/portal-checklist')
        ->where('unavailable', false)
        ->where('company', 'Acme')
        ->where('application.id', $this->application->id)
        ->where('application.section', 1)
        ->where('application.workplace', 'Calle Hidalgo 45, Centro, Querétaro, Querétaro')
        ->has('instructions')
        ->has('notice')
        ->where('consent.text', fn (string $text) => $text !== '')
        ->has('questions', 2, fn (Assert $question) => $question
            ->has('id')->has('heading')->has('text')->has('favorable')->has('sensitive')->etc())
        ->missing('answers'));
});

it('only shows the questions of the application checklist version and section', function (): void {
    addQuestion($this->checklist, 3, 'yes', section: 2);
    $v2 = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->checklist->work_center_id, 'version' => 2]);
    addQuestion($v2, 1, 'no');

    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->has('questions', 2));
});

it('is a 404 for an application that is not the person\'s, not found, retired or of another company', function (): void {
    $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $theirs = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $other->id])->id])->id, 'checklist_id' => $this->checklist->id]);

    $this->get(route('portal.checklist.show', [$this->token, $theirs->id]))->assertNotFound();
    $this->post(route('portal.checklist.store', [$this->token, $theirs->id]), form())->assertNotFound();
    $this->get('/portal/'.$this->token.'/checklists/not-a-uuid')->assertNotFound();
    $this->get(route('portal.checklist.show', [$this->token, (string) Str::uuid()]))->assertNotFound();

    $this->workplace->update(['retired_at' => now()]);
    $this->get($this->url)->assertNotFound();
});

it('tells a bad link apart from nothing: the unavailable page, like the portal', function (): void {
    $this->get(route('portal.checklist.show', ['nope', $this->application->id]))
        ->assertInertia(fn (Assert $page) => $page->component('telework/portal-checklist')->where('unavailable', true)->where('questions', [])
            ->where('token', null)->where('company', null)->where('application', null)->where('instructions', '')->where('notice', '')->where('consent', null));
    $this->post(route('portal.checklist.store', ['nope', $this->application->id]), form())->assertNotFound();
});

it('sends the token of the link that opened the list', function (): void {
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('token', $this->token));
});

it('sends a sent list back to the portal', function (): void {
    $this->post(route('portal.checklist.store', [$this->token, $this->application->id]), form())->assertRedirect(route('portal.show', $this->token));

    $this->get($this->url)->assertRedirect(route('portal.show', $this->token));
});

it('saves the answers, the note and a re-encoded photo, then returns to the portal', function (): void {
    $photo = new UploadedFile(__DIR__.'/../../Fixtures/photo-with-gps.jpg', 'foto.jpg', null, null, true);

    $this->post(route('portal.checklist.store', [$this->token, $this->application->id]), form(['special_needs' => 'Escritorio alto', "photo_{$this->yes->id}_0" => $photo, 'photo_general_0' => $photo]))
        ->assertRedirect(route('portal.show', $this->token))
        ->assertSessionHasNoErrors();

    $application = ChecklistApplication::query()->findOrFail($this->application->id);
    expect($application->submitted_at)->not->toBeNull()
        ->and($application->special_needs)->toBe('Escritorio alto')
        ->and(ChecklistAnswer::query()->count())->toBe(2)
        ->and(ChecklistPhoto::query()->count())->toBe(2);

    foreach (ChecklistPhoto::query()->get() as $stored) {
        expect($stored->path)->toStartWith("checklists/{$this->tenant->id}/{$application->id}/");
        Storage::disk('telework_files')->assertExists($stored->path);
        expect(@exif_read_data(Storage::disk('telework_files')->path($stored->path)))->not->toHaveKey('GPSLatitude');
    }
});

it('keeps nothing when a photo is not an image: no answers, no files', function (): void {
    $good = new UploadedFile(__DIR__.'/../../Fixtures/photo.png', 'foto.png', null, null, true);
    $bad = UploadedFile::fake()->createWithContent('foto.jpg', '%PDF-1.4 not an image');

    $this->post($this->url, form(['photo_general_0' => $good, 'photo_general_1' => $bad]))->assertSessionHasErrors('photos');

    expect(ChecklistAnswer::query()->count())->toBe(0)->and(Storage::disk('telework_files')->allFiles())->toBe([])
        ->and(ChecklistApplication::query()->findOrFail($this->application->id)->submitted_at)->toBeNull();
});

it('reports the rules of the answers as validation errors', function (): void {
    $this->from($this->url)->post($this->url, form(['risk_notes' => []]))
        ->assertRedirect($this->url)
        ->assertSessionHasErrors(['risk_notes.'.$this->yes->id]);
    $this->from($this->url)->post($this->url, ['consent' => '1'])->assertSessionHasErrors(['answers']);
    $this->from($this->url)->post($this->url, form(['risk_notes' => ['x' => str_repeat('a', 2001)]]))->assertSessionHasErrors(['risk_notes.x']);
});

it('says the list was already sent on a second post', function (): void {
    $this->post($this->url, form())->assertRedirect();

    $this->from($this->url)->post($this->url, form())->assertSessionHasErrors(['application' => 'Esta lista ya se envió.']);
});

it('throttles the sends per link', function (): void {
    foreach (range(1, 10) as $_) {
        $this->post($this->url, [])->assertRedirect();
    }

    $this->post($this->url, [])->assertStatus(429);
});

it('never puts individual answers in the props of its pages', function (): void {
    $this->post($this->url, form())->assertRedirect();

    $json = json_encode($this->get(route('portal.show', $this->token))->viewData('page')['props'], JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('Cable suelto')->not->toContain($this->yes->id);
});

it('opens nothing for a person whose telework period ended, to read or to send', function (): void {
    Teleworker::query()->update(['status' => TeleworkerStatus::Ended, 'started_at' => now()->subDay(), 'ended_at' => now(), 'end_reason' => EndReason::Other]);

    $this->get($this->url)->assertNotFound();
    $this->post($this->url, form())->assertNotFound();
    expect(ChecklistApplication::query()->findOrFail($this->application->id)->submitted_at)->toBeNull();
});

it('takes at most 10 photos per question and 10 for the list', function (int $count, bool $accepted): void {
    $photos = [];
    foreach (range(0, $count - 1) as $n) {
        $photos["photo_general_{$n}"] = new UploadedFile(__DIR__.'/../../Fixtures/photo.png', "{$n}.png", null, null, true);
        $photos["photo_{$this->yes->id}_{$n}"] = new UploadedFile(__DIR__.'/../../Fixtures/photo.png', "{$n}.png", null, null, true);
    }

    $response = $this->post($this->url, form($photos));

    if ($accepted) {
        $response->assertSessionHasNoErrors();
        expect(ChecklistPhoto::query()->count())->toBe($count * 2);
    } else {
        $response->assertSessionHasErrors('photos');
        expect(ChecklistPhoto::query()->count())->toBe(0)->and(Storage::disk('telework_files')->allFiles())->toBe([]);
    }
})->with([[10, true], [11, false]]);

it('takes photos of the whole list with consent', function (): void {
    $photo = new UploadedFile(__DIR__.'/../../Fixtures/photo.png', 'lugar.png', null, null, true);

    $this->post($this->url, form(['photo_general_0' => $photo]))->assertSessionHasNoErrors();

    expect(ChecklistPhoto::query()->whereNull('question_id')->count())->toBe(1);
});

it('lets the person finish with the link they opened after reminders were mailed, which carry the same link', function (): void {
    $opened = $this->token;

    expect(resolve(IssuePortalLink::class)->handle($this->employee->id))->toBe($opened);
    $this->post(route('portal.checklist.store', [$opened, $this->application->id]), form())->assertSessionHasNoErrors()->assertRedirect(route('portal.show', $opened));

    expect($this->application->refresh()->submitted_at)->not->toBeNull();
});

it('does not take a submission with a link RH revoked', function (): void {
    $opened = $this->token;
    resolve(IssuePortalLink::class)->handle($this->employee->id, revoke: true);

    $this->post(route('portal.checklist.store', [$opened, $this->application->id]), form())->assertNotFound();

    expect($this->application->refresh()->submitted_at)->toBeNull();
});
