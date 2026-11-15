import type { Auth } from '@/types';

/** The platform role a person of Humetría's staff holds, as the sidebar says it; null for everyone else. */
export function staffLabel(
    auth: Pick<Auth, 'isSuperadmin' | 'isStaff'>,
): string | null {
    if (auth.isSuperadmin) {
        return 'Superadmin';
    }

    return auth.isStaff ? 'Soporte' : null;
}
