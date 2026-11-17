import { describe, expect, it } from 'vite-plus/test';
import {
    FRESH,
    FRESH_SESSION,
    type Moment,
    mayInvite,
    rememberedOf,
    sessionOf,
    suggestionsOn,
    whenAccepted,
    whenNeverAgain,
    whenNotNow,
    whenOpened,
    whenShown,
    whenTurnedOn,
} from '@/lib/assistant/invitation-rules';

const MINUTE = 60_000;
const DAY = 24 * 60 * MINUTE;
const T0 = Date.UTC(2026, 10, 16, 15, 0, 0);

/** A person who arrived at the page of campaigns at T0, with something real to say, and nothing open on the page. */
function at(elapsed: number, change: Partial<Moment> = {}): Moment {
    return {
        now: T0 + elapsed,
        arrivedAt: T0,
        page: 'campaigns',
        hasMessage: true,
        busy: false,
        ...change,
    };
}

describe('mayInvite: when it shows', () => {
    it('waits a full minute on the page', () => {
        expect(mayInvite(FRESH, FRESH_SESSION, at(MINUTE - 1))).toBe(false);
        expect(mayInvite(FRESH, FRESH_SESSION, at(MINUTE))).toBe(true);
    });

    it('never shows without something real to say, on a page with no invitation, or while something is open', () => {
        expect(
            mayInvite(FRESH, FRESH_SESSION, at(MINUTE, { hasMessage: false })),
        ).toBe(false);
        expect(
            mayInvite(FRESH, FRESH_SESSION, at(MINUTE, { page: null })),
        ).toBe(false);
        expect(
            mayInvite(FRESH, FRESH_SESSION, at(MINUTE, { busy: true })),
        ).toBe(false);
    });

    it('waits ten minutes after the last one, on any page', () => {
        const [shown, session] = whenShown(FRESH, FRESH_SESSION, {
            page: 'results',
            now: T0 + MINUTE,
        });

        expect(mayInvite(shown, session, at(11 * MINUTE - 1))).toBe(false);
        expect(mayInvite(shown, session, at(11 * MINUTE))).toBe(true);
    });

    it('shows at most twice in a session', () => {
        const [once, first] = whenShown(FRESH, FRESH_SESSION, {
            page: 'results',
            now: T0,
        });
        const [twice, second] = whenShown(once, first, {
            page: 'work_centers',
            now: T0 + 20 * MINUTE,
        });

        expect(mayInvite(once, first, at(40 * MINUTE))).toBe(true);
        expect(mayInvite(twice, second, at(40 * MINUTE))).toBe(false);
        expect(mayInvite(twice, FRESH_SESSION, at(40 * MINUTE))).toBe(true);
    });

    it('shows once a day on the same page', () => {
        const [shown] = whenShown(FRESH, FRESH_SESSION, {
            page: 'campaigns',
            now: T0,
        });

        expect(
            mayInvite(shown, FRESH_SESSION, at(DAY - 1, { arrivedAt: T0 })),
        ).toBe(false);
        expect(mayInvite(shown, FRESH_SESSION, at(DAY))).toBe(true);
        expect(
            mayInvite(
                shown,
                FRESH_SESSION,
                at(DAY - 1, { page: 'interventions' }),
            ),
        ).toBe(true);
    });

    it('does not invite again in a session once the person opened Humi', () => {
        expect(mayInvite(FRESH, whenOpened(FRESH_SESSION), at(MINUTE))).toBe(
            false,
        );
    });
});

describe('the answers of the person', () => {
    it('turns the suggestions off for seven days after two «Ahora no» in a row', () => {
        const once = whenNotNow(FRESH, T0);
        const twice = whenNotNow(once, T0 + 20 * MINUTE);
        const back = T0 + 20 * MINUTE + 7 * DAY;

        expect(mayInvite(once, FRESH_SESSION, at(30 * MINUTE))).toBe(true);
        expect(suggestionsOn(twice, back - 1)).toBe(false);
        expect(mayInvite(twice, FRESH_SESSION, at(back - 1 - T0))).toBe(false);
        expect(suggestionsOn(twice, back)).toBe(true);
        expect(mayInvite(twice, FRESH_SESSION, at(back - T0))).toBe(true);
        expect(twice.notNowInARow).toBe(0);
    });

    it('starts the count of «Ahora no» again when the person accepts one', () => {
        const [once, session] = whenAccepted(
            whenNotNow(FRESH, T0),
            FRESH_SESSION,
        );
        const again = whenNotNow(once, T0 + DAY);

        expect(session.opened).toBe(true);
        expect(suggestionsOn(again, T0 + DAY)).toBe(true);
    });

    it('turns them off until the person turns them on again with «No volver a sugerir»', () => {
        const off = whenNeverAgain(FRESH);

        expect(suggestionsOn(off, T0 + 365 * DAY)).toBe(false);
        expect(mayInvite(off, FRESH_SESSION, at(365 * DAY))).toBe(false);
        expect(suggestionsOn(whenTurnedOn(), T0)).toBe(true);
        expect(mayInvite(whenTurnedOn(), FRESH_SESSION, at(MINUTE))).toBe(true);
    });
});

describe('what the browser kept', () => {
    it('reads back what it wrote', () => {
        const [shown, session] = whenShown(
            whenNotNow(FRESH, T0),
            FRESH_SESSION,
            { page: 'results', now: T0 },
        );

        expect(rememberedOf(JSON.parse(JSON.stringify(shown)))).toEqual(shown);
        expect(sessionOf(JSON.parse(JSON.stringify(session)))).toEqual(session);
    });

    it('starts fresh from nothing, or from something it did not write', () => {
        expect(rememberedOf(null)).toEqual(FRESH);
        expect(rememberedOf('hola')).toEqual(FRESH);
        expect(
            rememberedOf({
                off: 'yes',
                mutedUntil: 'x',
                notNowInARow: -1,
                lastShownAt: null,
                shownOnPage: { results: 'ayer', campaigns: T0 },
            }),
        ).toEqual({ ...FRESH, shownOnPage: { campaigns: T0 } });
        expect(sessionOf({ shown: 'dos', opened: 1 })).toEqual(FRESH_SESSION);
    });
});
