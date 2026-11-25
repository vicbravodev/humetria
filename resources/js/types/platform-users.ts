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
};

/** The views of «Equipo Humetría»: the staff, and everyone with an account. */
export type PlatformUserTab = 'team' | 'people';

export type PlatformUserFilters = { q: string; tab: PlatformUserTab };

export type PlatformUserCounts = { team: number; people: number };
