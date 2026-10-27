/**
 * The first field, in page order, that has a validation error: where focus goes after a failed submit.
 */
export function firstInvalidField<T extends { name: string }>(
    fields: Iterable<T>,
    errors: Partial<Record<string, string>>,
): T | null {
    for (const field of fields) {
        if (errors[field.name] !== undefined) {
            return field;
        }
    }

    return null;
}
