import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { parseFollowUp } from '@/lib/complaints/follow-up';
import { normalizeCode } from '@/lib/complaints/receipt';
import { open as openUrl, withdraw } from '@/routes/complaints/follow-up';
import { store as messageUrl } from '@/routes/complaints/follow-up/messages';
import type { FollowUp } from '@/types';

type Body = { reference: string; key: string; body: string; page: number };

/**
 * The follow-up of one complaint: the reference and the key live in memory only, here, and go in the body of every request.
 * Nothing is written to the browser: closing the page forgets them, which is how it is meant to be.
 */
export function useFollowUp(code: string) {
    const [view, setView] = useState<FollowUp | undefined>(undefined);
    const [secret, setSecret] = useState({ reference: '', key: '' });
    const http = useHttp<Body, unknown>({
        reference: '',
        key: '',
        body: '',
        page: 1,
    });

    const send = (url: string, extra: Partial<Body>, onDone?: () => void) => {
        http.transform(() => ({ ...secret, body: '', page: 1, ...extra }));
        http.post(url, {
            onSuccess: (response) => {
                setView(parseFollowUp(response));
                onDone?.();
            },
        }).catch(() => undefined);
    };

    return {
        view,
        errors: http.errors,
        processing: http.processing,
        remember: setSecret,
        open: (reference: string, key: string) => {
            const plain = {
                reference: normalizeCode(reference),
                key: normalizeCode(key),
            };
            setSecret(plain);
            http.transform(() => ({ ...plain, body: '', page: 1 }));
            http.post(openUrl.url({ code }), {
                onSuccess: (response) => setView(parseFollowUp(response)),
            }).catch(() => undefined);
        },
        page: (page: number) => send(openUrl.url({ code }), { page }),
        reply: (body: string, onDone: () => void) =>
            send(messageUrl.url({ code }), { body }, onDone),
        withdraw: () => send(withdraw.url({ code }), {}),
    };
}
