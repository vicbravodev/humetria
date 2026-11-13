export type Receipt = { reference: string; key: string };

/** The receipt the server answers a submission with; undefined if the answer is anything else. */
export function parseReceipt(value: unknown): Receipt | undefined {
    if (
        typeof value === 'object' &&
        value !== null &&
        'reference' in value &&
        'key' in value &&
        typeof value.reference === 'string' &&
        typeof value.key === 'string'
    ) {
        return { reference: value.reference, key: value.key };
    }

    return undefined;
}

/** A code in groups of four to read it aloud or copy it by hand: ABCD EFGH. */
export function groupCode(code: string): string {
    return code.replace(/(.{4})(?=.)/g, '$1 ');
}

/** Back to the plain code, whatever spaces, dashes or case the person typed. */
export function normalizeCode(text: string): string {
    return text.replace(/[\s-]/g, '').toUpperCase();
}

/** What the person shares with themselves (a note, a message to their own phone): both codes and what they are for. */
export function shareText(reference: string, key: string): string {
    return `Mi queja\nReferencia: ${groupCode(reference)}\nClave: ${groupCode(key)}\nGuarde su clave: sin ella no se puede ver cómo va y no se puede recuperar.`;
}
