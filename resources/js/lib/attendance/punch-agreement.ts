/** The company's current agreement on the electronic record, as the server gives it to read (criterio 5.1). */
export type Agreement = { version: number; body: string };

type Json = Record<string, unknown>;

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** The agreement an answer of the server brings to read; null when it brings none it can read. */
export function agreementOf(body: unknown): Agreement | null {
    const agreement = isJson(body) ? body['agreement'] : null;

    return isJson(agreement) &&
        typeof agreement['version'] === 'number' &&
        typeof agreement['body'] === 'string'
        ? { version: agreement['version'], body: agreement['body'] }
        : null;
}

/** The agreement's sections: one per paragraph, its title up to the first period. */
export function agreementSections(
    body: string,
): { title: string; text: string }[] {
    return body.split('\n\n').map((paragraph) => {
        const end = paragraph.indexOf('. ');

        return end === -1
            ? { title: '', text: paragraph }
            : {
                  title: paragraph.slice(0, end),
                  text: paragraph.slice(end + 2),
              };
    });
}
