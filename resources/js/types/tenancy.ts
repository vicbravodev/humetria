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
};
