<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\Questionnaire;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

const LOG_PRIVACY_COMMENT = 'ZZ-comentario-único-91';

beforeEach(function (): void {
    $this->logPath = storage_path('logs/engagement-privacy-test.log');
    File::delete($this->logPath);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $this->logPath]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');

    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create();
    $this->survey = Survey::factory()->active(now()->addDays(10)->toDateString())->create(['tenant_id' => $tenant->id]);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'survey_id' => $this->survey->id,
        'employee_id' => Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id])->id,
        'token_hash' => Invitation::hashToken('the-token'),
    ]);
    $this->invitation = $invitation;
});

afterEach(function (): void {
    File::delete($this->logPath);
});

/** The survey of the test answered with a recommendation of 7 and the comment of the test. */
function submitLoggedSurvey(): Illuminate\Testing\TestResponse
{
    $answers = [];

    foreach (Questionnaire::presentedTo(test()->survey->items, false) as $item) {
        if ($item->scale->range() !== null) {
            $answers[$item->key] = $item->key === 'eng.recommend' ? 7 : 3;
        }
    }

    return test()->post(route('engagement.survey.submit', 'the-token'), ['answers' => $answers, 'comments' => ['open.best' => LOG_PRIVACY_COMMENT], 'minutes' => 6]);
}

it('redacts what a person answers, writes or is told, at any depth', function (string $key): void {
    Log::info('engagement.test', ['nested' => [$key => 'leak'], $key => ['leak']]);

    $line = json_decode(Str::afterLast(trim(File::get($this->logPath)), "\n"), true, flags: JSON_THROW_ON_ERROR);

    expect($line['context'][$key])->toBe('[REDACTED]')->and($line['context']['nested'][$key])->toBe('[REDACTED]');
})->with(['comment', 'comments', 'themes', 'heard', 'will_do', 'owner_name', 'answers', 'body', 'token', 'token_hash', 'prompt']);

it('writes no comment and no answer to any log line, and none to the audit trail, when someone opens and answers', function (): void {
    $this->get(route('engagement.survey.show', 'the-token'))->assertOk();
    submitLoggedSurvey()->assertRedirect();

    $log = File::get($this->logPath);
    $audit = json_encode(DB::table('audit_entries')->get(), JSON_THROW_ON_ERROR);

    expect($log)->toContain('engagement.response_recorded')
        ->not->toContain(LOG_PRIVACY_COMMENT)
        ->not->toContain('eng.recommend')
        ->not->toContain('clarity.goals')
        ->and($audit)->not->toContain(LOG_PRIVACY_COMMENT)->not->toContain('eng.recommend');
});

it('does not dump the comment when saving it fails', function (): void {
    // A failing insert of the comment: the failed query's message would quote its bindings.
    Comment::creating(fn (Comment $comment) => DB::select('select * from missing_table where body = ?', [$comment->body]));

    submitLoggedSurvey()->assertServerError();

    $log = File::get($this->logPath);
    expect($log)->toContain('database.query_failed')->not->toContain(LOG_PRIVACY_COMMENT);
});

it('leaves no value of the answer or the comments in any log message of any channel, in the audit trail or in any other table', function (): void {
    $texts = ['open.best' => 'ZZ-mejor-77', 'open.improve' => 'ZZ-mejorar-78', 'manager.keep' => 'ZZ-jefe-79'];
    $heard = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$heard): void {
        $heard[] = $event->message.json_encode($event->context, JSON_PARTIAL_OUTPUT_ON_ERROR);
    });

    $manager = Employee::factory()->create(['tenant_id' => $this->survey->tenant_id, 'name' => 'Jefa Prueba']);
    $this->invitation->update(['about_manager_id' => $manager->id, 'manager_group_id' => $manager->id, 'comment_group_id' => $manager->id]);

    $answers = [];
    foreach (Questionnaire::presentedTo($this->survey->items, true) as $item) {
        if ($item->scale->range() !== null) {
            $answers[$item->key] = $item->key === 'eng.recommend' ? 7 : 3;
        }
    }

    $this->get(route('engagement.survey.show', 'the-token'))->assertOk();
    $this->post(route('engagement.survey.submit', 'the-token'), ['answers' => $answers, 'comments' => $texts, 'minutes' => 6])->assertRedirect();
    expect(Comment::query()->withoutGlobalScopes()->count())->toBeGreaterThan(0);

    $everywhere = implode("\n", $heard).File::get($this->logPath);
    foreach ($texts as $text) {
        expect($everywhere)->not->toContain($text);
    }

    // No other table keeps what was written: the only home of a comment is its own table.
    foreach (DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' AND table_name <> 'engagement_comments'") as $table) {
        $rows = json_encode(DB::table($table->table_name)->get(), JSON_THROW_ON_ERROR | JSON_PARTIAL_OUTPUT_ON_ERROR);
        foreach ($texts as $text) {
            expect($rows)->not->toContain($text, "{$table->table_name} keeps a comment");
        }
    }
});
