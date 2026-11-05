import type { Option } from '@/lib/option-search';

type BenefitOption = Option & { unit: string };

function isBenefitOption(value: unknown): value is Option & { unit?: unknown } {
    return (
        typeof value === 'object' &&
        value !== null &&
        'value' in value &&
        'label' in value &&
        typeof value.value === 'string' &&
        typeof value.label === 'string'
    );
}

async function loadBenefitOptions(
    url: string,
    query: string,
    signal: AbortSignal,
): Promise<BenefitOption[]> {
    const response = await fetch(
        `${url}?${new URLSearchParams({ q: query }).toString()}`,
        { headers: { Accept: 'application/json' }, signal },
    );

    if (!response.ok) {
        throw new Error(`Options request failed: ${url}`);
    }

    const body: unknown = await response.json();

    return Array.isArray(body)
        ? body.filter(isBenefitOption).map((option) => ({
              value: option.value,
              label: option.label,
              unit: typeof option.unit === 'string' ? option.unit : '',
          }))
        : [];
}

/**
 * The benefits of the account that have what was typed, for a Combobox, and the unit of each one it has found (the
 * Combobox hands back only the value and the label): "pesos", or empty for one it has not found.
 */
export function benefitOptions(url: string) {
    const units = new Map<string, string>();

    return {
        source: {
            initial: [],
            minLength: 1,
            load: async (query: string, signal: AbortSignal) => {
                const found = await loadBenefitOptions(url, query, signal);

                for (const option of found) {
                    units.set(option.value, option.unit);
                }

                return found.map(({ value, label }) => ({ value, label }));
            },
        },
        unitOf: (value: string) => units.get(value) ?? '',
    };
}
