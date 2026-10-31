import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    Monitor,
    Moon,
    PanelLeft,
    Plus,
    Settings,
    Sun,
    Upload,
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
import { index as campaigns } from '@/routes/tenant/campaigns';
import { create as importCsv } from '@/routes/tenant/employees/import';

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

/** What a role may start from the palette inside a company: a new evaluation, loading employees. */
function companyActions(
    tenant: NavTenant | null,
    visit: Options['actions']['visit'],
): PaletteCommand[] {
    if (tenant === null) {
        return [];
    }

    const newEvaluation: PaletteCommand = {
        id: 'new-evaluation',
        label: 'Nueva evaluación',
        keywords: ['nom-035', 'campaña', 'lanzar'],
        group: 'Acciones',
        icon: Plus,
        run: () => visit(campaigns(tenant.slug)),
    };
    const importEmployees: PaletteCommand = {
        id: toUrl(importCsv(tenant.slug)),
        label: 'Cargar empleados desde CSV',
        keywords: ['importar', 'plantilla', 'personal'],
        group: 'Acciones',
        icon: Upload,
        run: () => visit(importCsv(tenant.slug)),
    };

    return [
        ...(tenant.can.manage_campaigns ? [newEvaluation] : []),
        ...(tenant.can.manage_organization ? [importEmployees] : []),
    ];
}

/** What the ⌘K palette offers: what this person can start, the pages they can open, the theme and the sidebar. */
export function paletteCommands({
    isSuperadmin,
    tenant,
    sidebarExpanded,
    impersonating,
    actions,
}: Options): PaletteCommand[] {
    const pages = [
        ...mainNavItems(tenant, isSuperadmin),
        ...complianceNavItems(tenant),
        ...(isSuperadmin ? superadminNavItems : []),
    ];

    return [
        ...companyActions(tenant, actions.visit),
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
