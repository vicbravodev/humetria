import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    Menu,
    Monitor,
    Moon,
    Plus,
    Settings,
    Sun,
    Upload,
    UserRound,
} from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { type NavTenant, settingsEntryHref } from '@/lib/app-navigation';
import { hasModule } from '@/lib/modules';
import { navSections } from '@/lib/nav-sections';
import type { Command } from '@/lib/commands';
import { toUrl } from '@/lib/utils';
import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as employees } from '@/routes/tenant/employees';
import { create as importCsv } from '@/routes/tenant/employees/import';
import type { TenantCan } from '@/types';
import type { Option } from '@/lib/option-search';

export type PaletteCommand = Command & {
    group: string;
    icon: LucideIcon;
    run: () => void;
};

type Options = {
    isStaff: boolean;
    isSuperadmin?: boolean | undefined;
    tenant: NavTenant | null;
    /** What the role may write right now: a read-only company has it off. The menu's `tenant.can` keeps what it may still read. */
    writing?: TenantCan | undefined;
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
    can: TenantCan | undefined,
    visit: Options['actions']['visit'],
): PaletteCommand[] {
    if (tenant === null || can === undefined) {
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
        ...(can.manage_campaigns &&
        tenant.type !== 'union' &&
        hasModule(tenant, 'nom035')
            ? [newEvaluation]
            : []),
        ...(can.manage_organization ? [importEmployees] : []),
    ];
}

/** What the ⌘K palette offers: what this person can start, the pages they can open, the theme and the sidebar. */
export function paletteCommands({
    isStaff,
    isSuperadmin,
    tenant,
    writing = tenant?.can,
    sidebarExpanded,
    impersonating,
    actions,
}: Options): PaletteCommand[] {
    const pages = navSections({ tenant, isStaff, isSuperadmin }).flatMap(
        (section) => section.items,
    );

    return [
        ...companyActions(tenant, writing, actions.visit),
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
            icon: Menu,
            run: actions.toggleSidebar,
        },
    ];
}

/**
 * The people a search found, as commands under «Personas»: each opens Empleados narrowed to their name (there is no
 * person page yet). The label is «Nombre · Puesto»; the server already matched them, so they are not filtered again.
 */
export function peopleCommands(
    people: Option[],
    tenantSlug: string,
    visit: Options['actions']['visit'],
): PaletteCommand[] {
    return people.map(({ value, label }) => ({
        id: `person-${value}`,
        label,
        group: 'Personas',
        icon: UserRound,
        run: () =>
            visit(
                employees(tenantSlug, {
                    query: { q: label.split(' · ')[0] ?? label },
                }),
            ),
    }));
}
