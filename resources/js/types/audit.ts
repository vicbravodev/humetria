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
    family: string;
    company: string;
    from: string;
    to: string;
};

export type AuditFamily = { value: string; label: string };
