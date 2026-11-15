<?php

declare(strict_types=1);

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

it('says Analytics and the attribution cookie are off today, not that the visitor consents', function (): void {
    $text = privacyNoticeText();

    expect(config('services.google_analytics.consent_default'))->toBe('denied')
        ->and($text)->toContain('Hoy no medimos')->toContain('_ga')
        ->and($text)->not->toContain('con tu consentimiento');
});

it('says the theme preference lasts a year, as the appearance hook writes it', function (): void {
    expect(privacyNoticeText())->toContain('en el almacenamiento local de tu navegador')
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/resources/js/hooks/use-appearance.tsx'))->toContain('days = 365');
});

it('keeps the legal items marked as pending review', function (): void {
    expect(substr_count(privacyNoticeText(), 'pendiente de revisión legal'))->toBe(1)
        ->and(privacyNoticeText())->toContain('${REVIEW}');
});
