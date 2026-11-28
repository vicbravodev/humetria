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
        'landing', // @pest-mutate-ignore: RemoveArrayItem
        'privacy-notice', // @pest-mutate-ignore: RemoveArrayItem
        'roadmap', // @pest-mutate-ignore: RemoveArrayItem
        'tool', // @pest-mutate-ignore: RemoveArrayItem
        'guide', // @pest-mutate-ignore: RemoveArrayItem
        'blog/index', // @pest-mutate-ignore: RemoveArrayItem
        'blog/show', // @pest-mutate-ignore: RemoveArrayItem
        'campaigns/survey', // @pest-mutate-ignore: RemoveArrayItem
        'engagement/survey', // @pest-mutate-ignore: RemoveArrayItem
        'engagement/portal-feedback', // @pest-mutate-ignore: RemoveArrayItem
        'engagement/portal-climate', // @pest-mutate-ignore: RemoveArrayItem
        'organization/portal-team', // @pest-mutate-ignore: RemoveArrayItem
        'disclosure/portal-policy', // @pest-mutate-ignore: RemoveArrayItem
        'results/portal-support', // @pest-mutate-ignore: RemoveArrayItem
        'results/shared-report', // @pest-mutate-ignore: RemoveArrayItem
        'attendance/kiosk', // @pest-mutate-ignore: RemoveArrayItem
        'attendance/punch', // @pest-mutate-ignore: RemoveArrayItem
        'attendance/week', // @pest-mutate-ignore: RemoveArrayItem
        'worker-access/enter', // @pest-mutate-ignore: RemoveArrayItem
        'worker-access/home', // @pest-mutate-ignore: RemoveArrayItem
        'worker-access/link', // @pest-mutate-ignore: RemoveArrayItem
        'worker-access/key', // @pest-mutate-ignore: RemoveArrayItem
        'worker-access/not-me', // @pest-mutate-ignore: RemoveArrayItem
        'worker-access/proof', // @pest-mutate-ignore: RemoveArrayItem
        'compensation/shared-report', // @pest-mutate-ignore: RemoveArrayItem
        'telework/applies', // @pest-mutate-ignore: RemoveArrayItem
        'telework/write', // @pest-mutate-ignore: RemoveArrayItem
        'disclosure/policy-write', // @pest-mutate-ignore: RemoveArrayItem
        'tenancy/applies', // @pest-mutate-ignore: RemoveArrayItem
        'tenancy/onboarding', // @pest-mutate-ignore: RemoveArrayItem
        'demos/door', // @pest-mutate-ignore: RemoveArrayItem
        'error', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /** The families that share a prefix. */
    public const array STANDALONE_PREFIXES = ['telework/portal', 'leave/portal', 'seating/portal', 'complaints-public/', 'disclosure-public/']; // @pest-mutate-ignore: RemoveArrayItem

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
        return match (true) { // @pest-mutate-ignore: TrueToFalse
            self::isStandalone($component) => [],
            str_starts_with($component, 'auth/'), $component === 'tenancy/accept-invitation' => ['resources/js/layouts/auth-layout.tsx'],
            str_starts_with($component, 'settings/') => ['resources/js/layouts/app-layout.tsx', 'resources/js/layouts/settings/layout.tsx'],
            default => ['resources/js/layouts/app-layout.tsx'],
        };
    }
}
