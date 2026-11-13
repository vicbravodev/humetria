const MAX_LENGTH = 50;

/** The address a company name suggests; the same text gives the same slug as PHP's Str::slug (up to 50 characters). */
export function slugify(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s_-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, MAX_LENGTH)
        .replace(/-$/, '');
}

/** Where a company will live, as it is read to someone: the app's address without the protocol, then /admin/ and its slug. */
export function companyUrl(origin: string, slug: string): string {
    return `${origin.replace(/^https?:\/\//, '')}/admin/${slug === '' ? '…' : slug}`;
}
