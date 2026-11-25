export type AuditPerson = { name: string; email: string };

export type AuditEntryRow = {
    id: string;
    at: string;
    event: string;
    actor: AuditPerson | null;
    impersonator: AuditPerson | null;
    company: { id: string; name: string } | null;
    subject: string | null;
    context: Record<string, string>;
};

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
