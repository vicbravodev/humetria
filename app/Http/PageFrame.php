<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Which frame an Inertia page renders in, as the server needs to know it: the first response preloads the chunk of the
 * frame, because the browser would find it only after the page's own code ran. A mirror of resources/js/lib/page-frame.ts
 * and resources/js/layouts/frames.ts; tests/Unit/PageFrameTest.php keeps the lists the same.
 */
final class PageFrame
{
    /** The exact names of the pages with their own frame (the strings between the brackets of page-frame.ts). */
    public const array STANDALONE_NAMES = [
        'landing',
        'campaigns/survey',
        'engagement/survey',
        'engagement/portal-feedback',
        'seating/portal',
        'engagement/portal-climate',
        'organization/portal-team',
        'disclosure/portal-policy',
        'results/portal-support',
        'results/shared-report',
        'attendance/kiosk',
        'attendance/enroll',
        'attendance/portal',
        'attendance/punch',
        'attendance/week',
        'compensation/shared-report',
        'telework/applies',
        'telework/write',
        'disclosure/policy-write',
        'tenancy/applies',
        'tenancy/onboarding',
        'error',
    ];

    /** The families that share a prefix. */
    public const array STANDALONE_PREFIXES = ['telework/portal', 'leave/portal', 'complaints-public/', 'disclosure-public/'];

    public static function isStandalone(string $component): bool
    {
        return in_array($component, self::STANDALONE_NAMES, true) || array_any(self::STANDALONE_PREFIXES, fn (string $prefix): bool => str_starts_with($component, $prefix));
    }

    /**
     * The source files of the frames a page renders in, outermost first.
     *
     * @return list<string>
     */
    public static function layouts(string $component): array
    {
        return match (true) {
            self::isStandalone($component) => [],
            str_starts_with($component, 'auth/'), $component === 'tenancy/accept-invitation' => ['resources/js/layouts/auth-layout.tsx'],
            str_starts_with($component, 'settings/') => ['resources/js/layouts/app-layout.tsx', 'resources/js/layouts/settings/layout.tsx'],
            default => ['resources/js/layouts/app-layout.tsx'],
        };
    }
}
