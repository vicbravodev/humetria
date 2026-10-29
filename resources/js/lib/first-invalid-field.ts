/**
 * The first field, in page order, that has a validation error: where focus goes after a failed submit.
 */
export function firstInvalidField<T extends { name: string }>(
    fields: Iterable<T>,
    errors: Partial<Record<string, string>>,
): T | null {
    for (const field of fields) {
        // A list input (`tools[]`) reports its error under the bare key (`tools`).
        if (errors[field.name.replace(/\[\]$/, '')] !== undefined) {
            return field;
        }
    }

    return null;
}
