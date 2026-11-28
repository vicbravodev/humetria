import type { PlatformUserFilters, PlatformUserRow } from '@/types';

export type UserAction =
    | 'reset'
    | 'two_factor'
    | 'grant_support'
    | 'revoke_support';

/** The address of a view of «Equipo Humetría»: its search and its view, each left out when it is the default. */
export function usersQuery(
    filters: PlatformUserFilters,
): Partial<Record<keyof PlatformUserFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.tab === 'team' ? {} : { tab: filters.tab }),
    };
}

/** The platform role as the list says it. */
export function platformRoleLabel(
    role: PlatformUserRow['platformRole'],
): string {
    return role === 'superadmin'
        ? 'Superadmin'
        : role === 'support'
          ? 'Soporte'
          : 'Sin rol de plataforma';
}

/**
 * What the console offers on a user: a link to create a new password for anyone; the rest for who is not a superadmin
 * (their second factor and their role are the platform's own lock), and taking two-factor off only where it is on.
 */
export function userActions(user: PlatformUserRow): UserAction[] {
    if (user.platformRole === 'superadmin') {
        return ['reset'];
    }

    return [
        'reset',
        ...(user.twoFactor ? (['two_factor'] as const) : []),
        user.platformRole === 'support' ? 'revoke_support' : 'grant_support',
    ];
}

/** The quick help's line under a person's name (tablero Equipo): what helps to tell why they cannot get in. */
export function helpLine(user: PlatformUserRow): string {
    const role =
        user.platformRole === null
            ? []
            : [platformRoleLabel(user.platformRole)];
    const companies =
        user.companies !== ''
            ? [user.companies]
            : role.length === 0
              ? ['Sin empresas']
              : [];

    return [
        user.email,
        ...role,
        ...companies,
        user.twoFactor ? '2FA activa' : 'Sin 2FA',
        user.lastLogin === null
            ? 'Nunca ha entrado'
            : `Último acceso ${user.lastLogin}`,
        ...(user.verified ? [] : ['Correo sin verificar']),
    ].join(' · ');
}

/** What the quick help offers on a person, as buttons: getting them back in, never their role. */
export function helpActions(user: PlatformUserRow): UserAction[] {
    return userActions(user).filter(
        (action) => action === 'reset' || action === 'two_factor',
    );
}

type Copy = {
    menu: string;
    title: string;
    description: string;
    confirm: string;
    destructive: boolean;
};

/** The words of each action's menu item and of the confirmation that names the person. */
export function actionCopy(action: UserAction, user: PlatformUserRow): Copy {
    const who = `${user.name} (${user.email})`;

    switch (action) {
        case 'reset':
            return {
                menu: 'Enviar enlace de contraseña',
                title: `¿Enviar a ${user.name} un enlace para crear su contraseña?`,
                description: `Le llega un correo a ${user.email} con un enlace que vence pronto. Su contraseña actual sigue valiendo hasta que la cambie.`,
                confirm: 'Enviar enlace',
                destructive: false,
            };
        case 'two_factor':
            return {
                menu: 'Quitar verificación en dos pasos',
                title: `¿Quitar la verificación en dos pasos de ${user.name}?`,
                description: `${who} podrá entrar solo con su contraseña hasta que vuelva a activarla. Confirma antes que de verdad es esa persona. Queda en la bitácora.`,
                confirm: 'Quitar verificación',
                destructive: true,
            };
        case 'grant_support':
            if (user.companies !== '') {
                return {
                    menu: 'Dar rol de soporte',
                    title: `¿Dar el rol de soporte a ${user.name}?`,
                    description: `${who} pertenece a empresas cliente (${user.companies}). Como soporte verá la consola y podrá entrar a todas las empresas como su owner, no solo a la suya. Confirma que es lo que quieres. Queda en la bitácora.`,
                    confirm: 'Sí, dar soporte a esta persona',
                    destructive: false,
                };
            }

            return {
                menu: 'Dar rol de soporte',
                title: `¿Dar el rol de soporte a ${user.name}?`,
                description: `${who} verá la consola y podrá entrar a las empresas como su owner, pero no cambiará nada de ellas. Queda en la bitácora.`,
                confirm: 'Dar soporte',
                destructive: false,
            };
        case 'revoke_support':
            return {
                menu: 'Quitar rol de soporte',
                title: `¿Quitar el rol de soporte a ${user.name}?`,
                description: `${who} ya no verá la consola. Su cuenta y sus empresas siguen como estaban. Queda en la bitácora.`,
                confirm: 'Quitar soporte',
                destructive: true,
            };
    }
}
