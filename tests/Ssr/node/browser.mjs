import { chromium } from 'playwright';

export const baseUrl = process.env.BASE_URL ?? 'http://127.0.0.1:8000';

/** Runs `body(context)` in a fresh Chromium context and always closes the browser. */
export async function withContext(options, body) {
    const browser = await chromium.launch();

    try {
        return await body(await browser.newContext(options));
    } finally {
        await browser.close();
    }
}
