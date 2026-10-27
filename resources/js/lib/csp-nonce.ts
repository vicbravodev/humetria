type NonceSource = {
    querySelector(selector: string): { nonce?: string } | null;
};

/**
 * Reads the Content-Security-Policy nonce that app.blade.php publishes for the
 * libraries that inject their own <script> or <style> tags.
 */
export function readCspNonce(
    page: NonceSource | undefined,
): string | undefined {
    return (
        page?.querySelector('meta[property="csp-nonce"]')?.nonce || undefined
    );
}

export const cspNonce = readCspNonce(globalThis.document);
