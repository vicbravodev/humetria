/**
 * Where a work center stands in the six-stage route of a standard (rediseño v2, boards V2-Ruta-*): the stages done, in
 * order, and a short line about the earliest one left. When the standard does not apply, `done` is empty and the line
 * says why ("Nadie teletrabaja en este centro").
 */
export type RouteProgress = {
    applies: boolean;
    done: boolean[];
    status: string;
};

/** A work center of the panel with its place in each route. */
export type CenterRoutes = {
    id: string;
    name: string;
    nom035: RouteProgress;
    nom037: RouteProgress;
    seating: RouteProgress;
};

/** Something to attend before anything else ("Atiende primero"), with its one action. Never what a complaint says. */
export type Urgency = {
    /** "NOM-037", "NOM-035", "Quejas". */
    norm: string;
    title: string;
    detail: string;
    action: string;
    href: string;
};

/** Something that falls due in the next seven days: "Mar 10 nov" and what. */
export type WeekItem = { day: string; text: string };

/** How many standards apply in how many centers, for the sentence under the greeting. */
export type DashboardSummary = { norms: number; centers: number };

/** A stage of the bar: done, the one the center is in, or still ahead. */
export type StageState = 'done' | 'current' | 'pending';
