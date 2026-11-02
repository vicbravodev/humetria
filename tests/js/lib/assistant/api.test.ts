import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    latestAnswerId,
    latestConversationId,
    requestStop,
    sendFeedback,
} from '@/lib/assistant/api';

function respond(body: unknown, ok = true): Response {
    return new Response(JSON.stringify(body), { status: ok ? 200 : 500 });
}

describe('assistant api', () => {
    const fetcher = vi.fn();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetcher);
        vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=tok%3D' });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetcher.mockReset();
    });

    it('learns the conversation a first answer started: the latest one', async () => {
        fetcher.mockResolvedValue(
            respond({ data: [{ id: 'c-2' }, { id: 'c-1' }] }),
        );

        expect(await latestConversationId('acme')).toBe('c-2');
        expect(fetcher).toHaveBeenCalledWith(
            '/admin/acme/assistant/conversations',
            expect.objectContaining({
                headers: expect.objectContaining({
                    Accept: 'application/json',
                }),
            }),
        );
    });

    it('knows no conversation when there is none or the server fails', async () => {
        fetcher.mockResolvedValueOnce(respond({ data: [] }));
        expect(await latestConversationId('acme')).toBeNull();
        fetcher.mockResolvedValueOnce(respond({}, false));
        expect(await latestConversationId('acme')).toBeNull();
        fetcher.mockResolvedValueOnce(respond('nada'));
        expect(await latestConversationId('acme')).toBeNull();
    });

    it('finds the stored id of the last answer of a conversation', async () => {
        fetcher.mockResolvedValue(
            respond({
                messages: [
                    { id: 'm-2', role: 'assistant' },
                    { id: 'm-1', role: 'user' },
                ],
            }),
        );

        expect(await latestAnswerId('acme', 'c-1')).toBe('m-2');
        expect(fetcher).toHaveBeenCalledWith(
            '/admin/acme/assistant/conversations/c-1',
            expect.anything(),
        );
    });

    it('finds no answer when the conversation has none or cannot be read', async () => {
        fetcher.mockResolvedValueOnce(
            respond({ messages: [{ id: 'm-1', role: 'user' }] }),
        );
        expect(await latestAnswerId('acme', 'c-1')).toBeNull();
        fetcher.mockResolvedValueOnce(respond({}, false));
        expect(await latestAnswerId('acme', 'c-1')).toBeNull();
        fetcher.mockResolvedValueOnce(respond(null));
        expect(await latestAnswerId('acme', 'c-1')).toBeNull();
    });

    it('sends a thumb with the token of the session, and says whether the server took it', async () => {
        fetcher.mockResolvedValue(new Response(null, { status: 204 }));

        expect(
            await sendFeedback('acme', {
                messageId: 'm-1',
                rating: 'down',
                reason: 'incorrect',
            }),
        ).toBe(true);
        expect(fetcher).toHaveBeenCalledWith('/admin/acme/assistant/feedback', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': 'tok=',
            },
            body: JSON.stringify({
                message_id: 'm-1',
                rating: 'down',
                reason: 'incorrect',
            }),
        });

        fetcher.mockResolvedValue(new Response(null, { status: 500 }));
        expect(
            await sendFeedback('acme', {
                messageId: 'm-1',
                rating: 'up',
                reason: null,
            }),
        ).toBe(false);
    });

    it('presses stop on the server', async () => {
        fetcher.mockResolvedValue(new Response(null, { status: 204 }));

        await requestStop('acme');

        expect(fetcher).toHaveBeenCalledWith(
            '/admin/acme/assistant/chat/stop',
            expect.objectContaining({ method: 'POST' }),
        );
    });

    it('does not break the page when the network fails', async () => {
        fetcher.mockRejectedValue(new Error('offline'));

        expect(await latestConversationId('acme')).toBeNull();
        expect(
            await sendFeedback('acme', {
                messageId: 'm',
                rating: 'up',
                reason: null,
            }),
        ).toBe(false);
        await expect(requestStop('acme')).resolves.toBeUndefined();
    });
});
