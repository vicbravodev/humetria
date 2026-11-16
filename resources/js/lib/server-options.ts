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
        // A route that already carries a query (whom to leave out) keeps it.
        `${url}${url.includes('?') ? '&' : '?'}${new URLSearchParams({ q: query }).toString()}`,
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

/**
 * Where a Combobox gets options that live on the server: the route's first page as soon as it opens (all of it when it fits one page, then typing filters here), more from the route as letters narrow a longer list; `initial` shows first.
 * `allowNew` offers to add what was typed; `byName` makes each option's value its name, for a field that submits
 * names (an area, which may be new).
 */
export function serverSource(
    url: string,
    initial: Option[] = [],
    { allowNew = false, byName = false } = {},
) {
    return {
        initial,
        minLength: 0,
        allowNew,
        load: async (query: string, signal: AbortSignal) => {
            const found = await loadServerOptions(url, query, signal);

            return byName
                ? found.map(({ label }) => ({ value: label, label }))
                : found;
        },
    };
}
