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
    deleteConversation,
    latestConversationId,
    readLiveFacts,
    readConversation,
    requestStop,
    sendFeedback,
} from '@/lib/assistant/api';

function respond(body: unknown, ok = true): Response {
    return new Response(JSON.stringify(body), { status: ok ? 200 : 500 });
}

describe('assistant api: conversations', () => {
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
});

describe('assistant api: thumbs, stop and failures', () => {
    const fetcher = vi.fn();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetcher);
        vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=tok%3D' });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetcher.mockReset();
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

describe('assistant api: reading and deleting a conversation', () => {
    const fetcher = vi.fn();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetcher);
        vi.stubGlobal('document', { cookie: '' });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetcher.mockReset();
    });

    it('reads what the server keeps of a conversation, or nothing when it cannot', async () => {
        fetcher.mockResolvedValueOnce(respond({ messages: [] }));
        expect(await readConversation('acme', 'c-1')).toEqual({ messages: [] });
        expect(fetcher).toHaveBeenCalledWith(
            '/admin/acme/assistant/conversations/c-1',
            expect.anything(),
        );

        fetcher.mockResolvedValueOnce(respond({}, false));
        expect(await readConversation('acme', 'c-1')).toBeNull();
    });

    it('deletes a conversation and says whether the server did', async () => {
        fetcher.mockResolvedValueOnce(new Response(null, { status: 204 }));
        expect(await deleteConversation('acme', 'c-1')).toBe(true);
        expect(fetcher).toHaveBeenCalledWith(
            '/admin/acme/assistant/conversations/c-1',
            expect.objectContaining({ method: 'DELETE' }),
        );

        fetcher.mockResolvedValueOnce(new Response(null, { status: 404 }));
        expect(await deleteConversation('acme', 'c-1')).toBe(false);

        fetcher.mockRejectedValueOnce(new Error('offline'));
        expect(await deleteConversation('acme', 'c-1')).toBe(false);
    });
});

describe('assistant api: live facts', () => {
    const fetcher = vi.fn();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetcher);
        vi.stubGlobal('document', { cookie: '' });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetcher.mockReset();
    });

    it('reads the facts of the company, only those that are text', async () => {
        fetcher.mockResolvedValue(
            respond({
                facts: { pending: '1 pendiente', risk: 3, telework: null },
            }),
        );

        expect(await readLiveFacts('acme')).toEqual({ pending: '1 pendiente' });
        expect(String(fetcher.mock.calls[0]?.[0])).toBe(
            '/admin/acme/assistant/facts',
        );
    });

    it('has no facts when the server fails or answers something else', async () => {
        fetcher.mockResolvedValueOnce(respond({}, false));
        fetcher.mockResolvedValueOnce(respond({ facts: ['a'] }));
        fetcher.mockRejectedValueOnce(new Error('offline'));

        expect(await readLiveFacts('acme')).toEqual({});
        expect(await readLiveFacts('acme')).toEqual({});
        expect(await readLiveFacts('acme')).toEqual({});
    });
});
