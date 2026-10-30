import type { Option } from '@/lib/option-search';

function isOption(value: unknown): value is Option {
    return (
        typeof value === 'object' &&
        value !== null &&
        'value' in value &&
        'label' in value &&
        typeof value.value === 'string' &&
        typeof value.label === 'string'
    );
}

/**
 * The options a server route finds for what was typed (`?q=`), for a Combobox whose options live on the server. Only
 * what has the shape of an option is kept.
 */
export async function loadServerOptions(
    url: string,
    query: string,
    signal: AbortSignal,
): Promise<Option[]> {
    const response = await fetch(
        `${url}?${new URLSearchParams({ q: query }).toString()}`,
        { headers: { Accept: 'application/json' }, signal },
    );

    if (!response.ok) {
        throw new Error(`Options request failed: ${url}`);
    }

    const body: unknown = await response.json();

    return Array.isArray(body)
        ? body
              .filter(isOption)
              .map((option) => ({ value: option.value, label: option.label }))
        : [];
}
