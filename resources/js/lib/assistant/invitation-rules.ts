/**
 * When the closed bubble of Humi may invite, as pure functions of a clock the caller passes (never the real one): after a
 * minute on the page, then at most once every ten minutes, twice a session and once a day on the same page; never while
 * something is open, never without something real to say, never again in a session once Humi was opened. Two «Ahora no»
 * in a row turn the suggestions off for seven days, «No volver a sugerir» until the person turns them on.
 */

const MINUTE = 60_000;
const DAY = 24 * 60 * MINUTE;

const DWELL = MINUTE;
const GAP = 10 * MINUTE;
const PER_SESSION = 2;
const SAME_PAGE = DAY;
const MUTED_FOR = 7 * DAY;
const NOT_NOW_TO_MUTE = 2;

/** What the browser keeps for the person in the company, between sessions. Times are milliseconds since the epoch. */
export type Remembered = {
    off: boolean;
    mutedUntil: number | null;
    notNowInARow: number;
    lastShownAt: number | null;
    /** When it last showed on each page that invites. */
    shownOnPage: Readonly<Record<string, number>>;
};

/** What the browser keeps while the tab lives. */
export type Session = { shown: number; opened: boolean };

/** Where the person is now: the page that invites (null for any other), since when, and whether anything is open. */
export type Moment = {
    now: number;
    arrivedAt: number;
    page: string | null;
    hasMessage: boolean;
    /** Humi, a dialog or the first steps tour is open. */
    busy: boolean;
};

export const FRESH: Remembered = {
    off: false,
    mutedUntil: null,
    notNowInARow: 0,
    lastShownAt: null,
    shownOnPage: {},
};

export const FRESH_SESSION: Session = { shown: 0, opened: false };

/** Whether the person has the suggestions on now: not turned off, nor resting after two «Ahora no». */
export function suggestionsOn(remembered: Remembered, now: number): boolean {
    return (
        !remembered.off &&
        (remembered.mutedUntil === null || now >= remembered.mutedUntil)
    );
}

function nothingToSay(moment: Moment): boolean {
    return !moment.hasMessage || moment.busy;
}

function tooSoon(
    remembered: Remembered,
    moment: Moment,
    page: string,
): boolean {
    const onPage = remembered.shownOnPage[page];

    return (
        moment.now - moment.arrivedAt < DWELL ||
        (remembered.lastShownAt !== null &&
            moment.now - remembered.lastShownAt < GAP) ||
        (onPage !== undefined && moment.now - onPage < SAME_PAGE)
    );
}

export function mayInvite(
    remembered: Remembered,
    session: Session,
    moment: Moment,
): boolean {
    if (
        moment.page === null ||
        nothingToSay(moment) ||
        session.opened ||
        session.shown >= PER_SESSION
    ) {
        return false;
    }

    return (
        suggestionsOn(remembered, moment.now) &&
        !tooSoon(remembered, moment, moment.page)
    );
}

/** It showed on `page` at `now`. */
export function whenShown(
    remembered: Remembered,
    session: Session,
    { page, now }: { page: string; now: number },
): [Remembered, Session] {
    return [
        {
            ...remembered,
            lastShownAt: now,
            shownOnPage: { ...remembered.shownOnPage, [page]: now },
        },
        { ...session, shown: session.shown + 1 },
    ];
}

/** «Ahora no»: the second in a row rests the suggestions for seven days and starts the count again. */
export function whenNotNow(remembered: Remembered, now: number): Remembered {
    const inARow = remembered.notNowInARow + 1;

    return inARow >= NOT_NOW_TO_MUTE
        ? { ...remembered, notNowInARow: 0, mutedUntil: now + MUTED_FOR }
        : { ...remembered, notNowInARow: inARow };
}

/** «Preguntarle a Humi»: it opens Humi, so no more invitations this session, and the «Ahora no» start again. */
export function whenAccepted(
    remembered: Remembered,
    session: Session,
): [Remembered, Session] {
    return [{ ...remembered, notNowInARow: 0 }, whenOpened(session)];
}

/** Humi opened by any way: the bubble does not invite again this session. */
export function whenOpened(session: Session): Session {
    return { ...session, opened: true };
}

export function whenNeverAgain(remembered: Remembered): Remembered {
    return { ...remembered, off: true };
}

/** The person turned the suggestions on again: everything they declined is forgotten. */
export function whenTurnedOn(): Remembered {
    return FRESH;
}

function field(value: unknown, key: string): unknown {
    return typeof value === 'object' && value !== null && key in value
        ? Object.entries(value).find(([name]) => name === key)?.[1]
        : undefined;
}

function timeOf(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

/** What the browser kept, read back; anything it did not write counts as nothing. */
export function rememberedOf(value: unknown): Remembered {
    const inARow = field(value, 'notNowInARow');
    const pages = field(value, 'shownOnPage');

    return {
        off: field(value, 'off') === true,
        mutedUntil: timeOf(field(value, 'mutedUntil')),
        notNowInARow:
            typeof inARow === 'number' && Number.isInteger(inARow) && inARow > 0
                ? inARow
                : 0,
        lastShownAt: timeOf(field(value, 'lastShownAt')),
        shownOnPage:
            typeof pages === 'object' && pages !== null
                ? Object.fromEntries(
                      Object.entries(pages).filter(
                          (entry): entry is [string, number] =>
                              timeOf(entry[1]) !== null,
                      ),
                  )
                : {},
    };
}

export function sessionOf(value: unknown): Session {
    const shown = field(value, 'shown');

    return {
        shown:
            typeof shown === 'number' && Number.isInteger(shown) && shown > 0
                ? shown
                : 0,
        opened: field(value, 'opened') === true,
    };
}
