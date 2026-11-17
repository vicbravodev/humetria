import { loadServerOptions, serverSource } from '@/lib/server-options';

/**
 * The benefits of the account, for a Combobox: the first page as it opens, then what was typed, as `serverSource`
 * does, remembering the unit of each one found (the Combobox hands back only the value and the label):
 * "pesos", or empty for one it has not found. `except` leaves out the ones already added, which would do nothing.
 */
export function benefitOptions(url: string) {
    const units = new Map<string, string>();
    const source = {
        ...serverSource(url),
        load: async (query: string, signal: AbortSignal) => {
            const found = await loadServerOptions(url, query, signal);

            for (const option of found) {
                units.set(
                    option.value,
                    'unit' in option && typeof option.unit === 'string'
                        ? option.unit
                        : '',
                );
            }

            return found.map(({ value, label }) => ({ value, label }));
        },
    };

    return {
        source,
        except: (added: readonly string[]) => ({
            ...source,
            load: async (query: string, signal: AbortSignal) =>
                (await source.load(query, signal)).filter(
                    (option) => !added.includes(option.value),
                ),
        }),
        unitOf: (value: string) => units.get(value) ?? '',
    };
}
