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

export type PlatformUserFilters = { q: string };
