import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    Monitor,
    Moon,
    PanelLeft,
    Settings,
    Sun,
} from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import {
    complianceNavItems,
    mainNavItems,
    type NavTenant,
    settingsEntryHref,
    superadminNavItems,
} from '@/lib/app-navigation';
import type { Command } from '@/lib/commands';
import { toUrl } from '@/lib/utils';

export type PaletteCommand = Command & {
    group: string;
    icon: LucideIcon;
    run: () => void;
};

type Options = {
    isSuperadmin: boolean;
    tenant: NavTenant | null;
    sidebarExpanded: boolean;
    impersonating: boolean;
    actions: {
        visit: (href: NonNullable<InertiaLinkProps['href']>) => void;
        setTheme: (appearance: Appearance) => void;
        toggleSidebar: () => void;
    };
};

const THEMES: { id: Appearance; label: string; icon: LucideIcon }[] = [
    { id: 'light', label: 'Usar tema claro', icon: Sun },
    { id: 'dark', label: 'Usar tema oscuro', icon: Moon },
    { id: 'system', label: 'Usar el tema del sistema', icon: Monitor },
];

/** What the ⌘K palette offers: the pages this person can open, the theme and the sidebar. */
export function paletteCommands({
    isSuperadmin,
    tenant,
    sidebarExpanded,
    impersonating,
    actions,
}: Options): PaletteCommand[] {
    const pages = [
        ...mainNavItems(tenant, isSuperadmin),
        ...complianceNavItems,
        ...(isSuperadmin ? superadminNavItems : []),
    ];

    return [
        ...pages.map(({ title, href, icon }) => ({
            id: toUrl(href),
            label: title,
            group: 'Ir a',
            icon: icon ?? ArrowRight,
            run: () => actions.visit(href),
        })),
        {
            id: 'settings',
            label: 'Configuración',
            keywords: ['perfil', 'cuenta', 'contraseña'],
            group: 'Ir a',
            icon: Settings,
            run: () => actions.visit(settingsEntryHref(impersonating)),
        },
        ...THEMES.map(({ id, label, icon }) => ({
            id,
            label,
            keywords: ['apariencia', 'modo'],
            group: 'Tema',
            icon,
            run: () => actions.setTheme(id),
        })),
        {
            id: 'sidebar',
            label: sidebarExpanded ? 'Contraer menú' : 'Expandir menú',
            keywords: ['barra lateral'],
            group: 'Vista',
            icon: PanelLeft,
            run: actions.toggleSidebar,
        },
    ];
}
