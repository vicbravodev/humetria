/**
 * The Content-Security-Policy nonce of this page, published by app.blade.php for
 * the libraries that inject their own <script> or <style> tags.
 */
export const cspNonce =
    document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')
        ?.nonce || undefined;
