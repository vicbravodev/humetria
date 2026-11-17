<?php

declare(strict_types=1);

use App\Http\Analytics;
use App\Http\Consent;
use App\Http\IndexablePages;
use App\Http\SsrPages;

/*
 * The rules of CLAUDE.md and the SSR operations manual must name what the code really is: a rename of a class or a
 * variable that leaves them behind fails here. docs/superpowers/specs/2026-11-14-landing-seo-design-v2.md, section 12.9.
 */

function repoFile(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

it('names in CLAUDE.md the classes that decide indexing, server rendering, analytics and consent', function (): void {
    $rules = repoFile('CLAUDE.md');

    foreach ([IndexablePages::class, SsrPages::class, Analytics::class, Consent::class] as $class) {
        expect(str_contains($rules, $class))->toBeTrue("CLAUDE.md does not mention {$class}");
    }

    expect($rules)->toContain('X-Robots-Tag')->toContain('<a href>')->toContain('docs/operations/ssr.md');
});

it('documents in the manual every variable the SSR and the analytics read', function (): void {
    $manual = repoFile('docs/operations/ssr.md');

    foreach ([
        'INERTIA_SSR_ENABLED', 'INERTIA_SSR_URL', 'INERTIA_SSR_TIMEOUT', 'INERTIA_SSR_ENSURE_BUNDLE',
        'HUMETRIA_TAG', 'HUMETRIA_IMAGE', 'HUMETRIA_SSR_IMAGE', 'HUMETRIA_PULL_POLICY',
        'POSTHOG_PROJECT_TOKEN', 'POSTHOG_CONSENT_DEFAULT', 'DOKPLOY_WEBHOOK_URL',
    ] as $variable) {
        expect(str_contains($manual, $variable))->toBeTrue("The manual does not mention {$variable}");
    }
});

it('documents in the manual every signal the SSR leaves', function (): void {
    $manual = repoFile('docs/operations/ssr.md');

    foreach (['X-Rendered-By', 'ssr.render_failed', 'ssr.rendered', 'check-landing.mjs', 'synthetic.yml', 'build-revision', 'app-revision', 'ssr:circuit-open'] as $signal) {
        expect(str_contains($manual, $signal))->toBeTrue("The manual does not mention {$signal}");
    }
});

it('documents in the observability manual every signal the alerts read, as the code spells it', function (): void {
    $manual = repoFile('docs/operations/observability.md');
    $code = repoFile('app/Providers/AppServiceProvider.php').repoFile('routes/console.php').repoFile('app/Support/PostHogExceptions.php')
        .repoFile('app/Support/SendPostHogEvent.php').repoFile('app/Modules/Assistant/Listeners/SendLlmAnalytics.php').repoFile('config/logging.php');

    foreach (['queue.job_failed', 'queue.long_wait', 'schedule.command_failed', 'schedule.heartbeat', 'posthog.dispatch_failed', 'posthog.send_failed', '$exception', '$ai_generation', 'LOG_POSTHOG_LEVEL'] as $signal) {
        expect(str_contains($code, $signal))->toBeTrue("The code no longer says {$signal}")
            ->and(str_contains($manual, $signal))->toBeTrue("The observability manual does not mention {$signal}");
    }

    expect($manual)->toContain('LOG_STACK=stderr,posthog')->toContain(App\Logging\PostHogLogs::URL);
});

it('spells the bundle variable the way config/inertia.php reads it, and not the way the package does', function (): void {
    $names = [repoFile('docs/operations/ssr.md'), repoFile('.env.example'), repoFile('.env.production.example')];

    expect(repoFile('config/inertia.php'))->toContain("env('INERTIA_SSR_ENSURE_BUNDLE',");

    foreach ($names as $text) {
        expect($text)->toContain('INERTIA_SSR_ENSURE_BUNDLE')->not->toMatch('/INERTIA_SSR_ENSURE_BUNDLE_EXISTS(?!`)/');
    }
});

it('gives in the demo manual the figures the code has', function (): void {
    // Line breaks as spaces: the manual wraps at 120 columns wherever it falls.
    $manual = (string) preg_replace('/\s+/', ' ', repoFile('docs/operations/demo.md'));
    $config = require dirname(__DIR__, 2).'/config/complaints.php';
    $leave = require dirname(__DIR__, 2).'/config/leave.php';
    $door = (new ReflectionClassConstant(App\Modules\Demos\Http\Controllers\DemoRoomController::class, 'DOOR_MINUTES'))->getValue();
    $doorPeople = count(array_filter(Database\Seeders\Demo\DemoRoster::people(), fn ($person): bool => $person->reservedForDoor));

    foreach ([
        "acepta {$config['rate_limits']['demo_submissions_per_hour']} envíos por hora",
        "{$config['rate_limits']['demo_submissions_per_day']} al día por dirección",
        'a lo más **'.App\Modules\Demos\Support\DemoRoomPage::FRESH_DAYS.' días**',
        'más de '.App\Modules\Demos\Models\DemoRun::STALE_AFTER_MINUTES.' minutos',
        'Abrir sala ('.intdiv(is_int($door) ? $door : 0, 60).' h)',
        "hay {$doorPeople} reservados",
        'se corta a los '.intdiv((new App\Modules\Demos\Jobs\PrepareDemo('run'))->timeout, 60).' minutos',
        'a los ~'.$leave['escalation_business_days'].' días hábiles',
    ] as $figure) {
        expect(str_contains($manual, $figure))->toBeTrue("The demo manual does not say «{$figure}»");
    }
});
