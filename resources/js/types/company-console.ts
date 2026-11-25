import type { AuditEntryRow } from './audit';
import type { Paginated } from './demos';
import type {
    CompanyDetail,
    LimitRow,
    ModuleRow,
    OtherUsage,
    UsageMonth,
    UsageSummary,
} from './tenancy';

/** The plan as Billing tells it: its name and dates, never money. */
export type ConsolePlan = { name: string; since: string; renews_on: string };

export type CompanyMemberRow = {
    id: number;
    name: string;
    email: string;
    /** Its role there, a template or one of the company's own; null when it has none. */
    role: string | null;
    last_access: string | null;
};

export type CompanyInvitationRow = {
    id: string;
    name: string;
    email: string;
    status_label: string;
    expires_on: string;
};

/** A note of the staff about the company; the company never sees it. */
export type TenantNoteRow = {
    id: string;
    body: string;
    /** Who wrote it; null once that person is gone. */
    author: string | null;
    at: string;
};

/** What a company's page in the console receives. */
export type CompanyPage = {
    company: CompanyDetail;
    plan: ConsolePlan | null;
    modules: ModuleRow[];
    limits: LimitRow[];
    usage: UsageSummary;
    other_usage: OtherUsage[];
    usage_history: Paginated<UsageMonth>;
    members: Paginated<CompanyMemberRow>;
    invitations: Paginated<CompanyInvitationRow>;
    notes: Paginated<TenantNoteRow>;
    /** Its audit trail, for whoever may read it; null for the rest. */
    activity: Paginated<AuditEntryRow> | null;
    can_manage: boolean;
    can_audit: boolean;
};
