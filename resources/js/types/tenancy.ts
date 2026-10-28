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

type PendingInvitation = { trade_name: string; email: string; name: string };

/** What the invitation link page receives: the state says what the person reaching it can do. */
export type InvitationPage = { token: string } & (
    | { state: 'invalid'; trade_name: null; email: null; name: null }
    | ({ state: 'new_account' } & PendingInvitation)
    | ({ state: 'sign_in' | 'confirm' | 'other_account' } & PendingInvitation)
);
