import type { DemoRoom, DemoRoomSession, DemoTour } from '@/types';

/** «El centro y su gente» always goes first: about four minutes of every session. */
export const BASE_MINUTES = 4;

/** The step a session's preparation reaches last, after telling the story (PrepareDemoSession). */
const STAGING_STEP = 'Sala de la sesión';

/** The step it reaches first, when there is a demo to wipe (DemoWorkspaceRunner). */
const WIPING_STEP = 'Borrando la demo';

/** Chooses a tour (at the end of the order) or takes it out. */
export function toggleTour(
    chosen: string[],
    key: string,
    on: boolean,
): string[] {
    const without = chosen.filter((other) => other !== key);

    return on ? [...without, key] : without;
}

/** Moves a chosen tour one place up (-1) or down (1); at an edge, or not chosen, the order stays. */
export function moveTour(chosen: string[], key: string, by: -1 | 1): string[] {
    const from = chosen.indexOf(key);
    const to = from + by;

    if (from === -1 || to < 0 || to >= chosen.length) {
        return chosen;
    }

    const order = [...chosen];
    order.splice(from, 1);
    order.splice(to, 0, key);

    return order;
}

/** About how long the session takes: the chosen tours and the one that always goes. */
export function sessionMinutes(tours: DemoTour[], chosen: string[]): number {
    return tours
        .filter((tour) => chosen.includes(tour.key))
        .reduce((sum, tour) => sum + tour.minutes, BASE_MINUTES);
}

/**
 * Which phase of a session's preparation goes on, from the step its run reached: wiping the demo (0), telling its story
 * (1) or staging the session's room (2).
 */
export function preparationPhase(step: string | null): 0 | 1 | 2 {
    if (step === null || step === WIPING_STEP) {
        return 0;
    }

    return step === STAGING_STEP ? 2 : 1;
}

export function sessionTitle(client: string | null): string {
    return client === null ? 'Sesión de demostración' : `Sesión con ${client}`;
}

/** The tours a session shows, as a sentence: the chosen ones («El centro y su gente» goes without saying), or that one alone. */
export function tourNames(tours: DemoRoomSession['tours']): string {
    const chosen = tours.filter((tour) => tour.key !== 'base');

    return new Intl.ListFormat('es-MX', { type: 'conjunction' }).format(
        (chosen.length === 0 ? tours : chosen).map((tour) => tour.title),
    );
}

/** The sentence under the room's title: where the session stands or, without one, the demo company and how old it is. */
export function roomDescription({
    company,
    prepared,
    session,
}: Pick<DemoRoom, 'company' | 'prepared' | 'session'>): string {
    if (session !== null) {
        return sessionDescription(session);
    }

    if (company === null) {
        return 'La empresa demo para enseñar Humetría en vivo.';
    }

    return prepared === null
        ? company.name
        : `${company.name} · preparada ${prepared.ago} (${prepared.at})`;
}

function sessionDescription(session: DemoRoomSession): string {
    const client = session.client === null ? '' : ` con ${session.client}`;
    const sentences: Record<DemoRoomSession['status'], string> = {
        preparing: `Preparando la sesión${session.client === null ? '' : ` de ${session.client}`} · ${tourNames(session.tours)}`,
        active: `Sesión en curso${client} · empezó ${session.since}`,
        ending: `Terminando la sesión${client}`,
        failed: `La sesión${client} no quedó lista`,
    };

    return sentences[session.status];
}
