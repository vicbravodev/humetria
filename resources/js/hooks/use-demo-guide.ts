import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import {
    guideAnswer,
    nextCelebration,
    shownCelebration,
} from '@/lib/demos/guide';
import type { Celebration } from '@/lib/demos/guide';
import {
    guide as guideRoute,
    skip as skipRoute,
} from '@/routes/demo-room/session';
import type { DemoGuide } from '@/types/demo-guide';

const POLL_MS = 3000;

/** What an answer does to the guide (`guideAnswer`); undefined keeps it. Not JSON (a login page) is read as no body. */
async function answerOf(
    response: Response,
    poll: boolean,
): Promise<DemoGuide | null | undefined> {
    const body: unknown = await response.json().catch(() => undefined);

    return guideAnswer(response.redirected ? 0 : response.status, body, poll);
}

/** The line that celebrates, held while the guide stays on the step that came after it (the next poll brings none). */
function useCelebration(guide: DemoGuide | null): string | null {
    const [held, setHeld] = useState<Celebration | null>(null);
    const next = nextCelebration(held, guide);

    if (next !== held) {
        setHeld(next);
    }

    return shownCelebration(next, guide);
}

/**
 * The guide's requests, each numbered: only the answer of the latest one counts, so a poll that left before a skip and lands
 * after it cannot take the dock back a step. A new page's guide supersedes them all.
 */
function useGuideRequests(setGuide: (guide: DemoGuide | null) => void) {
    const latest = useRef(0);

    const send = useCallback(
        (request: () => Promise<Response>, poll: boolean): Promise<boolean> => {
            latest.current += 1;
            const mine = latest.current;

            return request()
                .then((response) => answerOf(response, poll))
                .then((answer) => {
                    if (mine === latest.current && answer !== undefined) {
                        setGuide(answer);
                    }

                    return answer !== undefined;
                })
                .catch(() => false);
        },
        [setGuide],
    );

    const poll = useCallback(
        () =>
            send(
                () =>
                    fetch(guideRoute.url(), {
                        headers: { Accept: 'application/json' },
                    }),
                true,
            ),
        [send],
    );

    const supersede = useCallback(() => {
        latest.current += 1;
    }, []);

    return { send, poll, supersede };
}

/**
 * The guide of the demo session: the page's (every full visit brings it fresh) and, while the dock is open, its own poll
 * every 3 s with fetch, never a partial reload (one would run the page's controller again, and some write to the audit
 * trail). `skip` moves on from the current step with the guide the server answers; refused, it asks for the guide again.
 */
export function useDemoGuide(polling: boolean): {
    guide: DemoGuide | null;
    celebration: string | null;
    skip: (step: string) => void;
} {
    const shared = usePage().props.demoGuide;
    const [guide, setGuide] = useState(shared);
    const [seen, setSeen] = useState(shared);
    const { send, poll, supersede } = useGuideRequests(setGuide);

    if (shared !== seen) {
        setSeen(shared);
        setGuide(shared);
    }

    const active = guide !== null;

    useEffect(() => {
        // A new page's guide supersedes any answer still on its way.
        supersede();

        if (!active || !polling) {
            return;
        }

        const timer = window.setInterval(() => void poll(), POLL_MS);

        return () => window.clearInterval(timer);
    }, [active, polling, shared, poll, supersede]);

    const skip = useCallback(
        (step: string) => {
            const xsrf = xsrfToken(document.cookie);

            void send(
                () =>
                    fetch(skipRoute.url(step), {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            ...(xsrf === null ? {} : { 'X-XSRF-TOKEN': xsrf }),
                        },
                    }),
                false,
            ).then((taken) => {
                if (!taken) {
                    void poll();
                }
            });
        },
        [send, poll],
    );

    return { guide, celebration: useCelebration(guide), skip };
}
