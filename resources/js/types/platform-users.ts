export type PlatformUserRow = {
    id: number;
    name: string;
    email: string;
    platformRole: 'superadmin' | 'support' | null;
    companies: string;
    twoFactor: boolean;
    verified: boolean;
    /** The day they last signed in; null when they never have. */
    lastLogin: string | null;
    /** How many times they entered a company as support in the last 30 days. */
    supportEntries: number;
};

/** The views of «Equipo Humetría»: the staff, everyone with an account, and what each platform role may do. */
export type PlatformUserTab = 'team' | 'people' | 'permissions';

/** One permission of the platform and whether each role holds it, as the gates answer. */
export type PlatformPermissionRow = {
    ability: string;
    label: string;
    support: boolean;
    superadmin: boolean;
};

export type PlatformUserFilters = { q: string; tab: PlatformUserTab };

export type PlatformUserCounts = { team: number; people: number };
