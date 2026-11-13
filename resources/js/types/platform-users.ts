export type PlatformUserRow = {
    id: number;
    name: string;
    email: string;
    platformRole: 'superadmin' | 'support' | null;
    companies: string;
    twoFactor: boolean;
    verified: boolean;
};

export type PlatformUserFilters = { q: string };
