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
        // a time-clock screen in every center of the demo (AttendanceStory), all issued by the room's one button
        'cada uno de los '.count(Database\Seeders\Demo\DemoRoster::CENTERS).' centros tiene la suya',
        'emite las '.count(Database\Seeders\Demo\DemoRoster::CENTERS),
        'se corta a los '.intdiv((new App\Modules\Demos\Jobs\PrepareDemo('run'))->timeout, 60).' minutos',
        'a los ~'.$leave['escalation_business_days'].' días hábiles',
    ] as $figure) {
        expect(str_contains($manual, $figure))->toBeTrue("The demo manual does not say «{$figure}»");
    }
});

/** Everything under app/Modules/Messaging, as one text: the signals and the commands the manuals name must be spelled in it. */
function messagingCode(): string
{
    $code = repoFile('routes/console.php');

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Modules/Messaging', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $code .= (string) file_get_contents($file->getPathname());
        }
    }

    return $code;
}

it('documents in the observability manual every WhatsApp signal the alerts read, as the code spells it', function (): void {
    $manual = repoFile('docs/operations/observability.md');
    $code = messagingCode();

    foreach (['whatsapp.paused_automatically', 'whatsapp.quality_changed', 'whatsapp.daily_cap_reached', 'whatsapp.webhook_malformed', 'whatsapp.orphans_discarded', 'mail.webhook_malformed', 'mail.webhook_secret_invalid'] as $signal) {
        expect(str_contains($code, $signal))->toBeTrue("The code no longer says {$signal}")
            ->and(str_contains($manual, $signal))->toBeTrue("The observability manual does not mention {$signal}");
    }

    expect($manual)->toContain('sin eventos en 24 horas');
});

it('documents in the WhatsApp manual the variables, the commands and the phases the code has', function (): void {
    $manual = repoFile('docs/operations/whatsapp.md');
    $config = repoFile('config/whatsapp.php').repoFile('config/messaging.php');
    $code = messagingCode();

    foreach ([
        'WHATSAPP_ENABLED', 'WHATSAPP_PAUSED', 'WHATSAPP_ACCESS_TOKEN', 'WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_APP_SECRET', 'WHATSAPP_APP_SECRET_PREVIOUS',
        'WHATSAPP_VERIFY_TOKEN', 'WHATSAPP_API_VERSION', 'WHATSAPP_MAX_PER_SECOND', 'WHATSAPP_DAILY_CAP_PER_COMPANY', 'WHATSAPP_PLATFORM_MONTHLY_CAP',
        'MESSAGING_RECIPIENT_KEYS', 'MESSAGING_RETENTION_MONTHS',
    ] as $variable) {
        expect(str_contains($config, $variable))->toBeTrue("The config no longer reads {$variable}")
            ->and(str_contains($manual, $variable))->toBeTrue("The WhatsApp manual does not mention {$variable}");
    }

    foreach (['messaging:resume', 'messaging:forget', 'messaging:purge', 'messaging:watch-failures', 'messaging:reconcile-unknown', 'messaging:replay-orphans', 'billing:whatsapp-reconcile'] as $command) {
        expect(str_contains($code, $command))->toBeTrue("The code has no command {$command}")
            ->and(str_contains($manual, $command))->toBeTrue("The WhatsApp manual does not mention {$command}");
    }

    foreach (['survey_invite_v1', 'engagement_invite_v1', 'UTILITY', 'Fase 2', 'Fase 3', 'x-hub-signature-256'] as $topic) {
        expect(str_contains(mb_strtolower($manual), mb_strtolower($topic)))->toBeTrue("The WhatsApp manual does not mention {$topic}");
    }
});

/** Everything under app/ and config/ as one text: what the PWA manual names must be spelled in it. */
function appAndConfigCode(): string
{
    $code = '';

    foreach (['app', 'config'] as $folder) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$folder, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $code .= (string) file_get_contents($file->getPathname());
            }
        }
    }

    return $code;
}

it('documents in the PWA manual every variable and signal the app installs with', function (): void {
    $manual = repoFile('docs/operations/pwa.md');
    $code = appAndConfigCode();
    $example = repoFile('.env.example');

    foreach ([
        'PWA_SW_KILL', 'ATTENDANCE_OFFLINE_ENABLED', 'ATTENDANCE_WEEK_BLOCK_UNCOVERED', 'APP_PREVIOUS_KEYS',
        'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY', 'VAPID_SUBJECT',
        'WORKER_ACCESS_DEVICE_KEYS_MAX', 'WORKER_ACCESS_OFFLINE_PASS_HOURS',
    ] as $variable) {
        expect(str_contains($code, "env('{$variable}'"))->toBeTrue("The config no longer reads {$variable}")
            ->and(str_contains($example, $variable.'='))->toBeTrue(".env.example does not have {$variable}")
            ->and(str_contains($manual, $variable))->toBeTrue("The PWA manual does not mention {$variable}");
    }

    foreach ([
        'push.unconfigured', 'attendance.offline_mark_received', 'attendance.offline_mark_rejected', 'attendance.offline_mark_already_recorded',
        'attendance.offline_queue_reported', 'attendance.week_uncovered_days_computed', 'interval_minutes', 'queue_age_minutes',
        'Service-Worker-Allowed', 'notifications:vapid-keys', 'npm run build:sw',
    ] as $signal) {
        expect(str_contains($code, $signal))->toBeTrue("The code no longer says {$signal}")
            ->and(str_contains($manual, $signal))->toBeTrue("The PWA manual does not mention {$signal}");
    }

    // Every push signal the code writes, as it spells it.
    preg_match_all("/'(push\\.[a-z_]+)'/", $code, $pushSignals);

    foreach (array_unique($pushSignals[1]) as $signal) {
        expect(str_contains($manual, $signal))->toBeTrue("The PWA manual does not mention {$signal}");
    }

    expect(repoFile('CLAUDE.md'))->toContain('docs/operations/pwa.md');
});

it('gives in the PWA manual the figures the code has', function (): void {
    // Line breaks as spaces: the manual wraps at 120 columns wherever it falls.
    $manual = (string) preg_replace('/\s+/', ' ', repoFile('docs/operations/pwa.md'));
    $offline = (require dirname(__DIR__, 2).'/config/attendance.php')['offline'];
    $access = require dirname(__DIR__, 2).'/config/worker_access.php';

    foreach ([
        "{$offline['ticket_batch']} boletos",
        "{$offline['ticket_horizon_days']} días",
        "{$offline['wide_interval_minutes']} min",
        "{$offline['count_declared_max_hours']} h",
        "{$offline['qr_old_window_minutes']} min",
        "{$offline['already_recorded_window_minutes']} min",
        "{$offline['review_age_days']} días",
        "{$offline['delivery_batch_max']} marcas",
        "{$access['device_keys_max']} llaves",
        "{$access['pass_hours']} h",
        App\Modules\Notifications\Actions\StoreSubscription::MAX_PER_PERSON.' suscripciones',
        App\Modules\Notifications\Actions\SendPush::TTL.' s',
    ] as $figure) {
        expect(str_contains($manual, $figure))->toBeTrue("The PWA manual does not say «{$figure}»");
    }
});
