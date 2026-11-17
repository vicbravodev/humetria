import type { FormDataConvertible } from '@inertiajs/core';

/** The domains typed one per line (or separated by commas or spaces), without blanks. */
export function splitDomains(text: string): string[] {
    return text
        .split(/[\s,;]+/)
        .map((domain) => domain.trim())
        .filter((domain) => domain !== '');
}

/** What the form sends: the typed domains as a list, whatever else it carries. */
export function ssoPayload(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    const { domains_text: text, ...rest } = data;

    return {
        ...rest,
        domains: splitDomains(typeof text === 'string' ? text : ''),
    };
}
