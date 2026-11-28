export type AuditPerson = { name: string; email: string };

export type AuditEntryRow = {
    id: string;
    at: string;
    /** The local day the table groups it under: «Hoy · 25 nov», «Ayer · 24 nov», «Domingo · 22 nov». */
    day: string;
    /** Its local time, «10:42». */
    time: string;
    /** The view of the trail it belongs to, if any. */
    kind: Exclude<keyof AuditCounts, 'all'> | null;
    /** Where it was done: «Consola», «Empresa», «Empresa, como soporte» or «Sistema». */
    origin: string;
    event: string;
    actor: AuditPerson | null;
    impersonator: AuditPerson | null;
    company: { id: string; name: string } | null;
    subject: string | null;
    context: Record<string, string>;
    /** What the entry changed, field by field; empty when it stored no before and after. */
    change: AuditChange[];
};

export type AuditChange = { field: string; before: string; after: string };

export type AuditFilters = {
    q: string;
    /** The view of the trail: '' for all of it, or one of AuditPages::KINDS. */
    kind: string;
    family: string;
    company: string;
    from: string;
    to: string;
};

export type AuditFamily = { value: string; label: string };

/** How many entries each view of the trail holds under the other filters. */
export type AuditCounts = {
    all: number;
    support: number;
    billing: number;
    results: number;
    roles: number;
};
