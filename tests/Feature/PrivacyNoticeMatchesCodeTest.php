<?php

declare(strict_types=1);

use App\Http\Analytics;
use App\Modules\Demos\Http\Middleware\RememberDemoAttribution;

/*
 * The privacy notice (resources/js/components/landing/privacy-notice-content.ts) says which cookies exist, what they are
 * called and how long they last. Those facts live in the code: the notice is pinned to them, so changing one without the
 * other fails here instead of leaving the notice saying something untrue.
 */
function privacyNoticeText(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/components/landing/privacy-notice-content.ts');
}

it('names the session cookie the app really sets', function (): void {
    expect(privacyNoticeText())->toContain(config()->string('session.cookie'))
        ->toContain('XSRF-TOKEN');
});

it('names the attribution cookie and its 90 days', function (): void {
    expect(privacyNoticeText())->toContain(RememberDemoAttribution::COOKIE)->toContain('90 días');
});

it('says PostHog measures the landing only, what it counts and that errors are reported, not that the visitor consents', function (): void {
    $text = privacyNoticeText();

    expect($text)->toContain('Medimos las visitas de la página de inicio')->toContain('PostHog')
        ->and($text)->toContain('qué botones y enlaces')->toContain('hasta dónde bajas')->toContain('errores técnicos')
        ->and($text)->not->toContain('Hoy no medimos')->not->toContain('no registramos tus clics')
        ->and($text)->not->toContain('Google')->not->toContain('_ga')
        ->and($text)->not->toContain('con tu consentimiento');
});

it('names the one PostHog host the code talks to, and says it sets no cookie and keeps no IP', function (): void {
    $text = privacyNoticeText();
    $options = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/analytics.ts');

    expect($text)->toContain(parse_url(Analytics::HOST, PHP_URL_HOST))
        ->and($text)->toContain('PostHog no escribe cookies')->toContain('No guardamos tu dirección IP')
        ->and($options)->toContain("persistence: 'localStorage'")->toContain('ip: false')
        ->and($options)->toContain('disable_session_recording: true')->toContain('autocapture: false');
});

it('says the theme preference lasts a year, as the appearance hook writes it', function (): void {
    expect(privacyNoticeText())->toContain('en el almacenamiento local de tu navegador')
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/resources/js/hooks/use-appearance.tsx'))->toContain('days = 365');
});

it('keeps the legal items marked as pending review', function (): void {
    expect(substr_count(privacyNoticeText(), 'pendiente de revisión legal'))->toBe(1)
        ->and(privacyNoticeText())->toContain('${REVIEW}');
});
