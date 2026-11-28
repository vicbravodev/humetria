import type { TenantView } from './tenancy';
import type { AuditEntryRow } from './audit';
import type { Paginated } from './demos';
import type {
    LimitRow,
    ModuleRow,
    OtherUsage,
    TenantRow,
    UsageMonth,
    UsageSummary,
} from './tenancy';

/** The company a console page is about. */
export type CompanyDetail = Pick<
    TenantRow,
    | 'id'
    | 'legal_name'
    | 'trade_name'
    | 'rfc'
    | 'slug'
    | 'type'
    | 'status'
    | 'status_label'
    | 'restriction_reason'
    | 'owner'
    | 'account_manager'
> & {
    /** The day it was registered, written for display. */
    created_on: string;
    /** The town of its first open work center; null without one. */
    city: string | null;
    logo_url: string | null;
    /** The latest sign-in of anyone who belongs to it; null when nobody has signed in. */
    last_access: string | null;
};

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

/** The account that pays the company now, and what that account (every company it pays) owes past due. */
export type CompanyPayer = {
    account_id: string;
    legal_name: string;
    kind_label: string;
    overdue_cents: number;
    days_past_due: number;
};

/** What a company's page in the console receives. */
export type CompanyPage = {
    company: CompanyDetail;
    plan: ConsolePlan | null;
    payer: CompanyPayer | null;
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

/** A company's plan as its row in the console shows it: what it pays a month before VAT (null while it does not pay) and when it renews. */
export type TenantPlan = {
    name: string;
    monthly_cents: number | null;
    renews_on: string;
};

/** The console's search, view and chips over its companies. */
export type TenantFilters = {
    q: string;
    status: TenantView;
    /** Who at Humetría answers for them: a staff id, «none», or empty for anyone. */
    manager?: string;
    /** «high»: seats almost used up (80 % or more). */
    usage?: string;
    /** A plan's key, as Billing names it. */
    plan?: string;
};
