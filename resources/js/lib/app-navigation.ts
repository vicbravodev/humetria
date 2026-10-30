import {
    Building2,
    ClipboardCheck,
    Factory,
    House,
    Inbox,
    UserCog,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import { edit as editProfile } from '@/routes/profile';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSecurity } from '@/routes/security';
import { dashboard as tenantDashboard } from '@/routes/tenant';
import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as employees } from '@/routes/tenant/employees';
import { index as members } from '@/routes/tenant/members';
import { index as workCenters } from '@/routes/tenant/work-centers';
import type { CurrentTenant, NavItem } from '@/types';

/** The company a page works in, as far as the navigation cares: where it lives and what the role allows. */
export type NavTenant = Pick<CurrentTenant, 'slug' | 'can'>;

/**
 * The pages of the panel, as the sidebar and the ⌘K palette list them. Panel is the current company's, or /admin
 * without one; the company's own pages appear only inside a company, and only those its role may open. A superadmin
 * outside a company gets none: /admin sends them to Empresas, which is their start.
 */
export const mainNavItems = (
    tenant: NavTenant | null,
    isSuperadmin: boolean,
): NavItem[] => {
    if (tenant === null) {
        return isSuperadmin
            ? []
            : [{ title: 'Panel', href: dashboard(), icon: House }];
    }

    const items: NavItem[] = [
        { title: 'Panel', href: tenantDashboard(tenant.slug), icon: House },
        {
            title: 'Centros de trabajo',
            href: workCenters(tenant.slug),
            icon: Factory,
        },
    ];

    const employeesItem = {
        title: 'Empleados',
        href: employees(tenant.slug),
        icon: Users,
    };
    const membersItem = {
        title: 'Usuarios',
        href: members(tenant.slug),
        icon: UserCog,
    };

    return [
        ...items,
        ...(tenant.can.manage_organization ? [employeesItem] : []),
        ...(tenant.can.manage_members ? [membersItem] : []),
    ];
};

/**
 * The compliance tools of the current company, for every role: who may change them is the page's to say. NOM-037 and
 * Ley Silla enter here when each gets its first page.
 */
export const complianceNavItems = (tenant: NavTenant | null): NavItem[] =>
    tenant === null
        ? []
        : [
              {
                  title: 'NOM-035',
                  href: campaigns(tenant.slug),
                  icon: ClipboardCheck,
              },
          ];

// Platform tools for Humetría staff; the server answers 404 to anyone else anyway.
export const superadminNavItems: NavItem[] = [
    { title: 'Empresas', href: companies(), icon: Building2 },
    { title: 'Solicitudes de demo', href: demoRequests(), icon: Inbox },
];

const appearanceItem: NavItem = {
    title: 'Apariencia',
    href: editAppearance(),
    icon: null,
};

/** The settings pages; while impersonating only Apariencia, since the owner's own account is off limits. */
export const settingsNavItems = (impersonating: boolean): NavItem[] =>
    impersonating
        ? [appearanceItem]
        : [
              { title: 'Perfil', href: editProfile(), icon: null },
              { title: 'Seguridad', href: editSecurity(), icon: null },
              appearanceItem,
          ];

/** Where "Configuración" in the user menu lands. */
export const settingsEntryHref = (impersonating: boolean): NavItem['href'] =>
    impersonating ? editAppearance() : editProfile();
