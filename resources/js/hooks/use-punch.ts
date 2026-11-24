import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { keepAnswer } from '@/hooks/use-offline-store';
import { forgetPushHere, rememberMarkHere } from '@/hooks/use-push-offer';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { currentPosition } from '@/lib/attendance/geolocation';
import type { LocationError } from '@/lib/attendance/geolocation';
import { firstStepNotice, stepThatProceeds } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';
import { punch, punchMessage } from '@/lib/attendance/punch';
import type { PunchPhase } from '@/lib/attendance/punch-phase';
import type {
    PunchDeps,
    PunchMode,
    PunchOutcome,
    Signer,
} from '@/lib/attendance/punch';
import { stateAfter } from '@/lib/attendance/punch-state';
import type { PunchState } from '@/lib/attendance/punch-state';
import { PUNCH_TIMEOUTS } from '@/lib/attendance/signed-request';
import { workerSlug } from '@/lib/pwa/sw-rules';
import type { SignedKey } from '@/lib/webauthn';

/** `navigator.credentials.get()` for a signature; null when the browser gave something else. */
export async function getKey(
    options: PublicKeyCredentialRequestOptions,
): Promise<SignedKey | null> {
    const credential = await navigator.credentials.get({ publicKey: options });

    return credential instanceof PublicKeyCredential &&
        credential.response instanceof AuthenticatorAssertionResponse
        ? {
              rawId: credential.rawId,
              type: credential.type,
              response: credential.response,
          }
        : null;
}

const NOBODY: PunchState = { person: null, marks: null, feedback: null };

/** The position now, or why there is none; none asked of a remote person, whose position the server drops. */
async function locate(ask: boolean) {
    if (!ask) {
        return { position: null, error: null };
    }

    const located = await currentPosition(
        'geolocation' in navigator ? navigator.geolocation : undefined,
    );

    return located.ok
        ? { position: located.position, error: null }
        : { position: null, error: located.error };
}

/** The screen of a browser that cannot use passkeys: nothing is asked of the server. */
function unsupportedState(state: PunchState): PunchState {
    return {
        ...state,
        feedback: {
            kind: 'error',
            text: punchMessage('unsupported'),
            reason: 'unsupported',
        },
    };
}

/** A remote person is asked for their position again only when a mark was refused for lack of one (someone else tapped). */
function asksAgain(asked: boolean, outcome: PunchOutcome): boolean {
    return (
        asked || (outcome.kind === 'failed' && outcome.reason === 'no_location')
    );
}

/**
 * What marking is signed and sent with: the browser's fetch, the device's key (saying which test it is on) and the CSRF token;
 * what the answer carries for the phone's queue (tickets, context) is kept. When the company marks without a connection the
 * requests have a time limit: 4 s for the server's challenge and 20 s for the mark, after which it goes to the queue.
 */
function punchDeps(
    setPhase: (phase: PunchPhase | null) => void,
    offline: boolean,
): PunchDeps {
    return {
        fetcher: fetch.bind(window),
        getKey: async (options) => {
            setPhase('signing');
            const key = await getKey(options);
            setPhase('verifying');

            return key;
        },
        xsrf: xsrfToken(document.cookie),
        onAnswer: keepAnswer,
        ...(offline ? { timeouts: PUNCH_TIMEOUTS } : {}),
    };
}

/**
 * «¿No es usted?»: this phone gives the notices of the person up first (best effort), then the browser forgets them. The company
 * is the one in the address.
 */
async function forgetHere(
    forgetUrl: string,
    onForgotten: () => void,
): Promise<void> {
    const slug = workerSlug(window.location.pathname);

    if (slug !== null) {
        await forgetPushHere(slug);
    }

    router.post(
        forgetUrl,
        {},
        { preserveScroll: true, onSuccess: onForgotten },
    );
}

/**
 * Marks from this device. The position is asked at every tap (whoever taps may not be who the screen shows) and kept
 * nowhere, except for a remote person, whose position the server drops: theirs is not asked, unless a mark is refused
 * for lack of one (someone else tapped). After each mark the screen shows only what the server said of the person it
 * verified. While it works it says which test it is on (position, the person's fingerprint, face or PIN, the server's
 * check). `accept` accepts the agreement read and marks the step that proceeds with one signature; `retry` marks again
 * what was tried, but never spends a signature on a step that the server already said does not proceed; `dismiss` closes
 * the answer. «¿No es usted?» makes the browser forget the person. A code read with the app's own camera wins over the
 * one the page opened with (the screen's QR, scanned outside); a second tap before the screen changes sends no second
 * mark. With `onOffline`, a mark that got no answer (no network, or too slow) goes to the phone's queue instead of failing.
 */
export function usePunch({
    optionsUrl,
    punchUrl,
    forgetUrl,
    qr,
    initial,
    mode,
    onOffline = null,
}: {
    optionsUrl: string;
    punchUrl: string;
    forgetUrl: string;
    qr: string | null;
    initial: Signer | null;
    mode: PunchMode;
    /** What to do with a mark that could not reach the server, with the code read for it; null: it fails. */
    onOffline?: ((type: MarkType, code: string | null) => void) | null;
}) {
    const [state, setState] = useState<PunchState>({
        ...NOBODY,
        person: initial,
    });
    const [phase, setPhase] = useState<PunchPhase | null>(null);
    const [location, setLocation] = useState<LocationError | null>(null);
    const [asksPosition, setAsksPosition] = useState(mode !== 'remote');
    const [tapped, setTapped] = useState<MarkType>('start');
    const [read, setRead] = useState<string | null>(null);
    const marking = useRef(false);
    const code = read ?? qr;

    const attempt = async (type: MarkType, agreementVersion?: number) => {
        setPhase('locating');
        setState({ ...state, feedback: null });
        const located = await locate(asksPosition);
        setLocation(located.error);
        const outcome = await punch(
            {
                optionsUrl,
                punchUrl,
                type,
                qr: code,
                position: located.position,
                ...(agreementVersion === undefined ? {} : { agreementVersion }),
            },
            punchDeps(setPhase, onOffline !== null),
        );
        setPhase(null);

        if (
            outcome.kind === 'failed' &&
            outcome.reason === 'offline' &&
            onOffline !== null
        ) {
            onOffline(type, code);

            return;
        }

        setAsksPosition(asksAgain(asksPosition, outcome));
        setState(stateAfter(outcome));

        if (outcome.kind === 'done') {
            // The first accepted mark is when the start may offer the notices.
            rememberMarkHere();
        }
    };

    const mark = async (type: MarkType, agreementVersion?: number) => {
        if (marking.current) {
            return;
        }

        setTapped(type);

        if (!('PublicKeyCredential' in window)) {
            setState(unsupportedState(state));

            return;
        }

        marking.current = true;

        try {
            await attempt(type, agreementVersion);
        } finally {
            marking.current = false;
        }
    };

    const next = state.person?.next ?? [];

    return {
        ...state,
        phase,
        location,
        /** The code the next mark carries: the one read in the app, or the one the page opened with. */
        code,
        /** The step the agreement is accepted with: the one tapped when it proceeds. */
        step: stepThatProceeds(tapped, next),
        /** The code was read inside the app. */
        readInApp: read !== null,
        /** Keeps the code the app's camera read for the marks that follow. */
        readCode: setRead,
        mark,
        accept: (version: number) =>
            void mark(stepThatProceeds(tapped, next), version),
        forget: () => forgetHere(forgetUrl, () => setState(NOBODY)),
        retry: () =>
            stepThatProceeds(tapped, next) === tapped
                ? void mark(tapped)
                : setState({
                      ...state,
                      feedback: { kind: 'error', text: firstStepNotice(next) },
                  }),
        dismiss: () => setState({ ...state, feedback: null }),
    };
}
