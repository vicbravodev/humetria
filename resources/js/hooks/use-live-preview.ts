import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { parsePreviewErrors } from '@/lib/compensation/scenario-draft';

/** Quiet time after the last change before asking: the server answers in tens of milliseconds. */
const DELAY = 250;

/** The form around the anchor as it would be sent, without its files; null without a form. */
function serialize(anchor: HTMLElement | null): URLSearchParams | null {
    const form = anchor?.closest('form');

    if (form === null || form === undefined) {
        return null;
    }

    const body = new URLSearchParams();

    for (const [key, value] of new FormData(form)) {
        if (typeof value === 'string') {
            body.append(key, value);
        }
    }

    return body;
}

/** The server's answer to a form, or null when it could not be asked. */
async function post(
    url: string,
    body: URLSearchParams,
    signal: AbortSignal,
): Promise<{ ok: boolean; json: unknown } | null> {
    try {
        const response = await fetch(url, {
            method: 'POST',
            body,
            signal,
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(document.cookie) ?? '',
            },
        });
        const json: unknown = await response.json();

        return { ok: response.ok, json };
    } catch {
        return null;
    }
}

/**
 * The live cost of a form that is being typed (decision A12): `anchorRef` goes on any element inside the form and `schedule` is
 * called on any change in it; once the changes stop it sends the whole form, as it would be saved, to `url`. The same form twice is asked once, a newer request
 * makes the older one stale, and a field the server refuses shows its message while the last good result stays; a field
 * still empty is not told it is missing until the form is saved.
 */
export function useLivePreview<T>(
    url: string,
    parse: (value: unknown) => T | null,
    /** Whether to ask as soon as the page opens; a form that starts empty waits for the first change. */
    immediate = true,
) {
    const anchorRef = useRef<HTMLDivElement>(null);
    const [result, setResult] = useState<T | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);
    const state = useRef<{
        timer?: ReturnType<typeof setTimeout>;
        sent: string;
        controller?: AbortController;
    }>({ sent: '' });

    const ask = async () => {
        const body = serialize(anchorRef.current);

        if (body === null || body.toString() === state.current.sent) {
            return;
        }

        state.current.sent = body.toString();
        state.current.controller?.abort();
        const controller = new AbortController();
        state.current.controller = controller;
        setLoading(true);
        const answer = await post(url, body, controller.signal);

        if (controller.signal.aborted) {
            return;
        }

        setLoading(false);

        if (answer !== null) {
            setErrors(answer.ok ? {} : parsePreviewErrors(answer.json, body));
            setResult((last) =>
                answer.ok ? (parse(answer.json) ?? last) : last,
            );
        }
    };

    const schedule = () => {
        clearTimeout(state.current.timer);
        state.current.timer = setTimeout(() => void ask(), DELAY);
    };

    const first = useEffectEvent(() => void ask());

    useEffect(() => {
        const current = state.current;
        // The first numbers come as soon as the page opens.
        if (immediate) {
            current.timer = setTimeout(first, 0);
        }

        return () => {
            clearTimeout(current.timer);
            current.controller?.abort();
        };
    }, [immediate]);

    return { anchorRef, result, errors, loading, schedule };
}
