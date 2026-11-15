<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;

/**
 * Whether a visitor's browser may be measured by a third party (PostHog). Today the answer is the owner's
 * configuration, `denied` until their lawyer says otherwise: there is no banner and no cookie of the visitor's decision.
 * When there is, this is the one place to read it.
 */
final class Consent
{
    public static function analyticsAllowed(Request $request): bool
    {
        return config('services.posthog.consent_default') === 'granted';
    }
}
