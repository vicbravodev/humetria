/** A row of table 10.3 as an inspector would ask for it (board V3-Inspeccion): in plain words, with its way to fix or see it. */
export type InspectionItem = {
    numeral: string;
    title: string;
    detail: string | null;
    state: 'done' | 'partial' | 'missing' | 'not_applicable';
    /** Null for whoever may not open it (the viewer). */
    href: string | null;
    action: string | null;
};

/** A row of table 10.3 on the route of a center (board V2-Ruta-N037): the stage (1 to 5) it belongs to. */
export type RouteRow = InspectionItem & { stage: number };

/** The accidents of a center nobody answered yet: who, when only one; since when the oldest waits. */
export type RouteAccidents = {
    person: string | null;
    count: number;
    /** Y-m-d. */
    since: string;
    /** "hace 8 meses". */
    waiting: string;
    href: string;
};

/** Something that waits for the Comisión in one of its centers (board V2-Comision). */
export type CommitteeTask = {
    kind: 'lists' | 'notices';
    workCenter: string;
    count: number;
    /** Whose lists wait, in the order they were sent; empty for notices. */
    people: string[];
    href: string;
};

/** A center the member sits on, with who sits with them. */
export type CommitteeSeat = {
    id: string;
    name: string;
    members: string[];
    href: string;
};
