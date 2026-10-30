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
    status:
        | 'not_started'
        | 'trial'
        | 'active'
        | 'trial_ended'
        | 'expired'
        | 'deactivated';
    status_label: string;
    status_date: string | null;
    /** Last paid day as Y-m-d in Mexico City time; null when never activated. */
    active_until: string | null;
    blocked: boolean;
    owner: TenantOwner | null;
    invitation: TenantInvitation | null;
};

type PendingInvitation = {
    trade_name: string;
    /** What the person joins as, written for display ("Te invitaron como RH de Acme"). */
    invited_as: string;
    /** Who sent it; null when no sender was recorded. */
    inviter: string | null;
    role: TenantRole;
    role_label: string;
    email: string;
    name: string;
};

/** What the invitation link page receives: the state says what the person reaching it can do. */
export type InvitationPage = { token: string; passwordRules: string } & (
    | {
          state: 'invalid';
          trade_name: null;
          invited_as: null;
          inviter: null;
          role: null;
          role_label: null;
          email: null;
          name: null;
      }
    | ({ state: 'new_account' } & PendingInvitation)
    | ({ state: 'sign_in' | 'confirm' | 'other_account' } & PendingInvitation)
);

export type TenantSummary = Pick<TenantRow, 'slug' | 'trade_name'>;

/** The company a request is working in, shared on every page under /admin/{slug}. */
export type CurrentTenant = TenantSummary &
    Pick<TenantRow, 'status' | 'blocked'> & {
        trial_days_left: number | null;
        role: TenantRole;
        role_label: string;
        /** What the person's role allows, so the page hides what the server would refuse. */
        can: {
            manage_organization: boolean;
            manage_members: boolean;
            manage_campaigns: boolean;
            view_trauma_screenings: boolean;
        };
    };

export type TenantRole = 'owner' | 'hr' | 'viewer';

/** One row of the list /admin shows a person who belongs to zero or several companies. */
export type CompanyLink = TenantSummary &
    Pick<TenantRow, 'status' | 'status_label' | 'blocked'>;

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
    /** Street to state in one line, already written for display. */
    address: string;
    /** The day it was archived, written for display; null while active. */
    archived_on: string | null;
    /** Its active employees. */
    employees: number;
    /** The NOM-035 guides that apply by that number: none with nobody, I up to 15, I and II up to 50, I and III above. */
    guides: ('I' | 'II' | 'III')[];
};

export type MexicanStateOption = { value: string; label: string };

export type WorkCentersView = 'active' | 'archived';

/** A person with access to the company, as «Usuarios» lists them. */
export type MemberRow = {
    id: number;
    name: string;
    email: string;
    role: TenantRole;
    role_label: string;
    is_you: boolean;
    /** The day they got access, written for display. */
    since: string;
};

/** An invitation to join the company that has not been accepted nor cancelled. */
export type MemberInvitationRow = {
    id: string;
    name: string;
    email: string;
    role: TenantRole;
    role_label: string;
    status: 'pending' | 'expired';
    status_label: string;
    expires_on: string;
};

export type RoleOption = { value: TenantRole; label: string };
