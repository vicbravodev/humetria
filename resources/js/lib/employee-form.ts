import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';

/** A name as the option a Combobox shows for it and submits: a category, a level, an area. None without a name. */
export function namedOption(name: string): Option | undefined {
    return name === '' ? undefined : { value: name, label: name };
}

/** The option of a catalog with that value; none for an empty or unknown value. */
export function catalogOption(
    options: Option[],
    value: string,
): Option | undefined {
    return value === '' ? undefined : options.find((o) => o.value === value);
}

/** The options with one first that clears the choice: a field that may stay empty can be emptied again. */
export function clearableOptions(options: Option[], none: string): Option[] {
    return [{ value: '', label: none }, ...options];
}

/**
 * Where a field of names that live on the server gets its options (a category, a level): searched there, a name that
 * is not there yet can be added, and before anything is typed the first option clears the choice.
 */
export function namedSource(
    url: string,
    current: Option | undefined,
    none: string,
) {
    return serverSource(
        url,
        [
            { value: '', label: none },
            ...(current === undefined ? [] : [current]),
        ],
        { allowNew: true, byName: true },
    );
}
