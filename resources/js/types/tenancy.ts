import type { TenantCan } from './tenant-can';
export type TenantOwner = {
    name: string;
    email: string;
};

export type TenantInvitation = {
    id: string;
    name: string;
    email: string;
    status: 'pending' | 'expired';
    status_label: string;
    /** Last valid day, already written for display ("4 nov 2026"). */
    expires_on: string;
};

export type TenantRow = {
    id: string;
    legal_name: string;
    trade_name: string;
    rfc: string;
    slug: string;
    type: 'company' | 'union';
    /** The logo its people see on their emails; null shows its name instead. */
    logo_url: string | null;
    status:
        | 'not_started'
        | 'trial'
        | 'active'
        | 'trial_ended'
        | 'expired'
        | 'read_only'
        | 'suspended'
        | 'archived';
    status_label: string;
    status_date: string | null;
    /** Why a person restricted it (read only, suspended, archived); null otherwise. */
    restriction_reason: string | null;
    /** Whether it records working time with Attendance: restricting it stops that. */
    uses_attendance: boolean;
    /** Last paid day as Y-m-d in Mexico City time; null when never activated. */
    active_until: string | null;
    blocked: boolean;
    /** The latest day anyone who belongs to the company signed in; null when nobody has. */
    last_access: string | null;
    /** The prospect of the demo request it was created from, to prefill the owner's invitation; null when it did not come from one. */
    demo_contact: { name: string; email: string } | null;
    owner: TenantOwner | null;
    invitation: TenantInvitation | null;
};

/** The views of the console's companies. */
export type TenantView =
    | 'all'
    | 'active'
    | 'trial'
    | 'expired'
    | 'read_only'
    | 'suspended'
    | 'archived';

export type TenantFilters = { q: string; status: TenantView };

export type TenantSummary = Pick<TenantRow, 'slug' | 'trade_name'>;

/** The company a request is working in, shared on every page under /admin/{slug}. */
export type CurrentTenant = TenantSummary &
    Pick<TenantRow, 'status' | 'blocked'> & {
        /** Its people read and export but change nothing: an ended trial or term, or a person's decision. */
        read_only: boolean;
        /** Why a person suspended or archived it (what its blocked notice says); null for any other state. */
        restriction_reason: string | null;
        /** Whether the account is a company or a union; fixed when it was created. */
        type: 'company' | 'union';
        /** The modules the company has (attendance, nom035, nom037, seating, complaints…); the others are hidden. */
        modules: string[];
        trial_days_left: number | null;
        role: CompanyRoleKey;
        role_label: string;
        /** What the person's role allows, so the page hides what the server would refuse: in a read-only company, nothing that writes. */
        can: TenantCan;
        /** What the role allows whatever the company's state: the menu keeps the pages a read-only company still reads. */
        permissions: TenantCan;
    };

export type TenantRole = 'owner' | 'hr' | 'viewer' | 'committee' | 'complaints';

/** A role a person holds in a company: a template, or one of the company's own (`custom_<uuid>`, shown by its label). */
export type CompanyRoleKey = TenantRole | `custom_${string}`;

/** One row of the list /admin shows a person who belongs to zero or several companies. */
export type CompanyLink = TenantSummary &
    Pick<TenantRow, 'status' | 'status_label' | 'blocked'> & {
        read_only: boolean;
    };

/** One work center as its company's list shows it. */
export type WorkCenterRow = {
    id: string;
    name: string;
    street: string;
    exterior_number: string;
    interior_number: string | null;
    neighborhood: string;
    postal_code: string;
    municipality: string;
    /** ISO 3166-2:MX code without the country, like "QUE". */
    state: string;
    main_activity: string;
    /** The IANA zone its working day is measured in, like "America/Tijuana". */
    timezone: string;
    /** That zone with its offset today and its city: «Hora del Noroeste, UTC−8 · Tijuana». */
    timezone_label: string;
    /** Street to state in one line, already written for display. */
    address: string;
    /** The day it was archived, written for display; null while active. */
    archived_on: string | null;
    /** Its active employees. */
    employees: number;
    /** The NOM-035 guides that apply by that number: none with nobody, I up to 15, I and II up to 50, I and III above. */
    guides: ('I' | 'II' | 'III')[];
    /** Its five largest areas, with their active people. */
    areas: { name: string; employees: number }[];
    /** How many other areas it has. */
    more_areas: number;
    /** Where it stands with its NOM-035 evaluations; null when it was never evaluated. */
    evaluation: WorkCenterEvaluationStanding | null;
};

export type WorkCenterEvaluationStanding = {
    state: 'draft' | 'running' | 'due' | 'overdue';
    /** The running one's last day, or the day the next one is (or was) due; null for a draft. */
    day: string | null;
    /** For «al día»: who answered the evaluation it closed with, of how many; null otherwise. */
    answered: number | null;
    target: number | null;
};

export type MexicanStateOption = { value: string; label: string };

export type WorkCentersView = 'active' | 'archived';

/** A person with access to the company, as «Usuarios» lists them. */
export type MemberRow = {
    id: number;
    name: string;
    email: string;
    role: CompanyRoleKey;
    role_label: string;
    /** How many permissions they have besides their role. */
    extra_permissions: number;
    is_you: boolean;
    /** The day they got access, written for display. */
    since: string;
};

/** An invitation to join the company that has not been accepted nor cancelled. */
export type MemberInvitationRow = {
    id: string;
    name: string;
    email: string;
    role: CompanyRoleKey;
    role_label: string;
    status: 'pending' | 'expired';
    status_label: string;
    expires_on: string;
};

export type RoleOption = { value: CompanyRoleKey; label: string };

/** A role an invitation can give, with what it switches on: the floor of its «Permisos adicionales». */
export type InvitableRole = RoleOption & { permissions: string[] };

/** Where a module of a company comes from: its plan, what it had before the plan, a person's grant, or a person's revocation. */
export type ModuleSource = 'plan' | 'legacy' | 'grant' | 'revoke';

/** A module of a company as its console page lists it. */
/** What a company has used of one limit of its plan, as its own pages show it; null when it has no plan. */
export type PlanLimit = {
    used: number;
    amount: number;
    remaining: number;
    over: boolean;
};

/** What a company has used of one limit of its plan (people or work centers), as the console shows it. */
export type LimitRow = {
    resource: 'seats' | 'centers';
    label: string;
    used: number;
    amount: number;
    remaining: number;
    /** It already had more than the limit when it was set: it keeps them and cannot add. */
    over: boolean;
};

/** Humi's tokens of the current month for a company, in the console. */
export type UsageSummary = {
    period_label: string;
    used: number;
    /** Held for answers still being written. */
    reserved: number;
    /** The plan's quota plus what a person added this month. */
    quota: number;
    granted: number;
    remaining: number;
};

/** A meter that only measures (campaign email, files): this month's figure against the plan's quota. */
export type OtherUsage = {
    meter: string;
    label: string;
    unit: string;
    used: number;
    quota: number;
    remaining: number;
};

/** A month of Humi's use. */
export type UsageMonth = {
    period: string;
    label: string;
    used: number;
    /** The quota the month had when last asked; null when nobody asked. */
    quota: number | null;
};

export type ModuleRow = {
    key: string;
    label: string;
    enabled: boolean;
    source: ModuleSource | null;
    source_label: string | null;
    reason: string | null;
    /** The day it stops being on, written for display; null when it does not end. */
    ends_on: string | null;
    /** False for the tools the catalog names but nobody has built yet. */
    exists_in_code: boolean;
};

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
>;

/** A Mexican time zone to choose for a work center, named with its offset today. */
export type TimezoneOption = { value: string; label: string };

/** How a work center's zone is suggested: by its state, or by its municipality where the state has more than one. */
export type TimezoneRules = {
    /** The zone of each state with one that is not the fallback, by its ISO code. */
    states: Record<string, string>;
    /** By state code, the municipalities (lower case, no accents) whose zone is not their state's. */
    municipalities: Record<string, Record<string, string>>;
    fallback: string;
};
