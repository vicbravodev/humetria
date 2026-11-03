import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    readTenantSettings,
    tokensText,
} from '@/lib/assistant/tenant-settings';

describe('readTenantSettings', () => {
    const fetcher = vi.fn();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetcher);
        vi.stubGlobal('document', { cookie: '' });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetcher.mockReset();
    });

    it('reads what the server says of Humi for a company', async () => {
        fetcher.mockResolvedValue(
            new Response(
                JSON.stringify({
                    enabled: false,
                    monthly_token_budget: 500,
                    default_monthly_token_budget: 2000000,
                    used_tokens: 120,
                }),
            ),
        );

        expect(await readTenantSettings('t-1')).toEqual({
            enabled: false,
            monthlyTokenBudget: 500,
            defaultBudget: 2000000,
            usedTokens: 120,
        });
        expect(fetcher).toHaveBeenCalledWith(
            '/admin/companies/t-1/assistant',
            expect.anything(),
        );
    });

    it('takes a budget of none as the default one', async () => {
        fetcher.mockResolvedValue(
            new Response(
                JSON.stringify({
                    enabled: true,
                    monthly_token_budget: null,
                    default_monthly_token_budget: 10,
                    used_tokens: 0,
                }),
            ),
        );

        expect(await readTenantSettings('t-1')).toMatchObject({
            enabled: true,
            monthlyTokenBudget: null,
        });
    });

    it('is nothing when the server fails, answers something else or the network is down', async () => {
        fetcher.mockResolvedValueOnce(new Response('{}', { status: 404 }));
        expect(await readTenantSettings('t-1')).toBeNull();
        fetcher.mockResolvedValueOnce(
            new Response(JSON.stringify({ enabled: 'si' })),
        );
        expect(await readTenantSettings('t-1')).toBeNull();
        fetcher.mockRejectedValueOnce(new Error('offline'));
        expect(await readTenantSettings('t-1')).toBeNull();
    });
});

describe('tokensText', () => {
    it('writes a count of tokens with thousands separated', () => {
        expect(tokensText(0)).toBe('0');
        expect(tokensText(2000000)).toBe('2,000,000');
    });
});
