import { Factory, House, UserCog, Users } from 'lucide-react';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSecurity } from '@/routes/security';
import { dashboard as tenantDashboard } from '@/routes/tenant';
import { index as employees } from '@/routes/tenant/employees';
import { index as members } from '@/routes/tenant/members';
import { index as workCenters } from '@/routes/tenant/work-centers';
import { complaintCasesItems } from '@/lib/complaints-navigation';
import { hasModule } from '@/lib/modules';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem, InCourse, NavItem, NavTenant } from '@/types';

export type { NavTenant } from '@/types';
export { complianceNavItems } from '@/lib/compliance-navigation';

/** A union has no compliance tools (the server answers 404 to them); everything else is a company's. */
const isUnion = (tenant: NavTenant): boolean => tenant.type === 'union';

/** The company's own pages the role may open: a union has no work centers, and its people are its members. */
const companyItems = (tenant: NavTenant): NavItem[] => [
    { title: 'Panel', href: tenantDashboard(tenant.slug), icon: House },
    ...(isUnion(tenant)
        ? []
        : [
              {
                  title: 'Centros de trabajo',
                  href: workCenters(tenant.slug),
                  icon: Factory,
              },
          ]),
    ...(tenant.can.manage_organization
        ? [
              {
                  title: isUnion(tenant) ? 'Agremiados' : 'Empleados',
                  href: employees(tenant.slug),
                  icon: Users,
              },
          ]
        : []),
    ...(tenant.can.manage_members
        ? [{ title: 'Usuarios', href: members(tenant.slug), icon: UserCog }]
        : []),
];

/**
 * The pages of the panel, as the sidebar and the ⌘K palette list them (board 40, V4-Menu-Roles). Panel is the current
 * company's, or /admin without one; the company's own pages appear only inside a company, and only those its role may
 * open. A superadmin outside a company gets none: /admin sends them to Empresas, which is their start. The committee's
 * only pages are NOM-037's and Ley Silla's (under Cumplimiento); the outside handler of complaints sees its cases and
 * nothing else of the company.
 */
export const mainNavItems = (
    tenant: NavTenant | null,
    isStaff: boolean,
): NavItem[] => {
    if (tenant === null) {
        return isStaff
            ? []
            : [{ title: 'Panel', href: dashboard(), icon: House }];
    }

    if (tenant.role === 'committee') {
        return [];
    }

    if (tenant.role === 'complaints') {
        return hasModule(tenant, 'complaints')
            ? complaintCasesItems(tenant)
            : [];
    }

    return companyItems(tenant);
};

/**
 * The sidebar's "Siguiente obligación": when the soonest evaluation in course closes ("en 11 días", "mañana",
 * "hoy"); null without one.
 */
export const nextObligation = (
    inCourse: InCourse | null,
): { lead: string; when: string } | null => {
    if (inCourse === null || inCourse.daysLeft === null) {
        return null;
    }

    const days = inCourse.daysLeft;
    const when = days <= 0 ? 'hoy' : days === 1 ? 'mañana' : `en ${days} días`;

    return { lead: 'La próxima evaluación cierra', when };
};

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

export type NavSection = { label: string; items: NavItem[] };

/**
 * The top bar's trail when a page sets none: on a sidebar page, its section and its name ("Cumplimiento › NOM-035");
 * below one, a link back to it. A page that opens other pages (Panel) is never taken for their parent.
 */
export const navTrail = (
    sections: NavSection[],
    path: string,
): BreadcrumbItem[] => {
    const all = sections.flatMap(({ label, items }) =>
        items.map((item) => ({ label, item, url: toUrl(item.href) })),
    );
    const exact = all.find(({ url }) => url === path);

    if (exact !== undefined) {
        return [{ title: exact.label }, { title: exact.item.title }];
    }

    const parent = all.find(
        ({ url }) =>
            path.startsWith(`${url}/`) &&
            !all.some((other) => other.url.startsWith(`${url}/`)),
    );

    return parent === undefined
        ? []
        : [{ title: parent.item.title, href: parent.item.href }];
};

/**
 * Which child of a sidebar entry is lit on a page: the one that is that page or claims it (`also`), or else the closest
 * one it hangs from (the dossier of a center lights "Centros"). One at most, so only one pill shows.
 */
export const activeChild = (
    children: NavItem[],
    path: string,
): NavItem | undefined =>
    children.find((child) =>
        [child.href, ...(child.also ?? [])].some(
            (href) => toUrl(href) === path,
        ),
    ) ??
    children
        .filter((child) => path.startsWith(`${toUrl(child.href)}/`))
        .toSorted((a, b) => toUrl(b.href).length - toUrl(a.href).length)[0];
