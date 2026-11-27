export type DemoRequestRow = {
    id: string;
    name: string;
    email: string;
    whatsapp: string;
    company: string;
    profile: string | null;
    workforce: string | null;
    tools: string[];
    message: string | null;
    received_at: string;
    status: DemoStatus;
    status_label: string;
    /** The team's own note; never shown to the person who asked. */
    note: string | null;
    /** The company it became, once it has. */
    tenant_id: string | null;
    /** The channel it arrived by; null is "no data", which is NOT direct traffic. */
    arrived_by: string | null;
    /** The first known channel, only when it is not the one it arrived by. */
    first_contact: string | null;
};

export type DemoStatus = 'new' | 'contacted' | 'converted' | 'discarded';

export type DemoView = 'all' | DemoStatus;

export type DemoFilters = { status: DemoView };

/** The part of a Laravel LengthAwarePaginator the console reads. */
export type Paginated<T> = {
    data: T[];
    total: number;
    /** The first and last row on this page, 1-based; null on an empty page. */
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** «Sala de demostración»: the demo company, the run preparing it, how old it is and what is shared live. */
export type DemoRoom = {
    company: { id: string; name: string } | null;
    run: DemoRunState | null;
    prepared: { ago: string; at: string; stale: boolean } | null;
    complaints: DemoShareLink | null;
    door: DemoDoor;
    /** The tours a session may show, «El centro y su gente» aside: it always goes first. */
    tours: DemoTour[];
    session: DemoRoomSession | null;
};

/** A tour a session may show: what it shows and about how long it takes. */
export type DemoTour = {
    key: string;
    title: string;
    summary: string;
    minutes: number;
};

/** The session in course, or the last one when its preparation failed: each tour's progress and the step that follows. */
export type DemoRoomSession = {
    client: string | null;
    status: 'preparing' | 'active' | 'ending' | 'failed';
    failed: boolean;
    /** Since when it is under way: «hace 12 minutos». */
    since: string;
    tours: { key: string; title: string; done: number; total: number }[];
    /** Where the guide goes on (a path inside the demo), while active and not finished. */
    next: {
        title: string;
        tour: string;
        index: number;
        total: number;
        href: string;
    } | null;
};

export type DemoRunState = {
    kind: 'prepare' | 'reset' | 'session';
    status: 'running' | 'finished' | 'failed';
    step: string | null;
    error_class: string | null;
};

/** A link to share in the room, with its QR as a data URI. */
export type DemoShareLink = { url: string; qr: string };

/** The door, open until «14:30» (Mexico City time) or closed; and the workers reserved for the audience: taken, free and all. */
export type DemoDoor = (
    | { open: true; closes_at: string }
    | { open: false; closes_at: null }
) & {
    claimed: number;
    free: number;
    total: number;
};

/** The demo request a company is about to be created from, to fill the dialog with. */
export type TenantPrefill = {
    demo_request_id: string;
    company: string;
    contact: string;
    email: string;
};
