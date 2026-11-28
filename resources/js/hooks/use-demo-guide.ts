import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import {
    guideAnswer,
    guideSequence,
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
 * The guide's requests, in the order `guideSequence` keeps: only the latest answer counts, so a poll that left before a skip
 * and lands after it cannot take the dock back a step, and no poll leaves while a skip is on its way. A new page's guide
 * supersedes them all.
 */
function useGuideRequests(setGuide: (guide: DemoGuide | null) => void) {
    const [sequence] = useState(guideSequence);

    const send = useCallback(
        (request: () => Promise<Response>, skip: boolean): Promise<boolean> => {
            const ticket = sequence.begin(skip);

            return request()
                .then((response) => answerOf(response, !skip))
                .catch(() => undefined)
                .then((answer) => {
                    if (sequence.settle(ticket, skip) && answer !== undefined) {
                        setGuide(answer);
                    }

                    return answer !== undefined;
                });
        },
        [sequence, setGuide],
    );

    const poll = useCallback(
        () =>
            send(
                () =>
                    fetch(guideRoute.url(), {
                        headers: { Accept: 'application/json' },
                    }),
                false,
            ),
        [send],
    );

    return { send, poll, sequence };
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
    const { send, poll, sequence } = useGuideRequests(setGuide);

    if (shared !== seen) {
        setSeen(shared);
        setGuide(shared);
    }

    const active = guide !== null;

    useEffect(() => {
        // A new page's guide supersedes any answer still on its way; hiding or showing the dock does not.
        sequence.supersede();
    }, [shared, sequence]);

    useEffect(() => {
        if (!active || !polling) {
            return;
        }

        const timer = window.setInterval(() => {
            if (!sequence.holdsPolls()) {
                void poll();
            }
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [active, polling, poll, sequence]);

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
                true,
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
