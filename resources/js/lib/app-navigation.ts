import {
    Factory,
    House,
    KeyRound,
    Layers,
    ShieldCheck,
    IdCard,
    UserCog,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSecurity } from '@/routes/security';
import { dashboard as tenantDashboard, plan } from '@/routes/tenant';
import { edit as privacy } from '@/routes/tenant/privacy';
import { index as employees } from '@/routes/tenant/employees';
import { index as members } from '@/routes/tenant/members';
import { index as roles } from '@/routes/tenant/roles';
import { index as workCenters } from '@/routes/tenant/work-centers';
import { index as workerAccess } from '@/routes/tenant/worker-access';
import {
    complaintCasesItems,
    handlesCasesOnly,
} from '@/lib/complaints-navigation';
import { hasModule } from '@/lib/modules';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem, InCourse, NavItem, NavTenant } from '@/types';

export type { NavTenant } from '@/types';
export { complianceNavItems } from '@/lib/compliance-navigation';

/** A union has no compliance tools (the server answers 404 to them); everything else is a company's. */
const isUnion = (tenant: NavTenant): boolean => tenant.type === 'union';

/**
 * The company's own pages the role may open: a union has no work centers, and its people are its members. Each shows only
 * where its permission is, so a role of the company's own sees no link that answers 403.
 */
const companyItems = (
    tenant: NavTenant,
    group: 'daily' | 'admin',
): NavItem[] => {
    const { can, slug } = tenant;
    const union = isUnion(tenant);
    const pages: [visible: boolean, item: NavItem, group: 'daily' | 'admin'][] =
        [
            // The panel is drawn from the work centers: without them it is not where anyone starts (StartRoute::PANEL_ALSO_NEEDS).
            [
                can.view_dashboard && can.view_work_centers,
                { title: 'Panel', href: tenantDashboard(slug), icon: House },
                'daily',
            ],
            [
                !union && can.view_work_centers,
                {
                    title: 'Centros de trabajo',
                    href: workCenters(slug),
                    icon: Factory,
                },
                'daily',
            ],
            [
                can.manage_organization,
                {
                    title: union ? 'Agremiados' : 'Empleados',
                    href: employees(slug),
                    icon: Users,
                },
                'daily',
            ],
            // The people's keys and invitations: every company has it, whatever its tools; a union has no people of its own here.
            [
                can.view_worker_access && !union,
                { title: 'Accesos', href: workerAccess(slug), icon: KeyRound },
                'daily',
            ],
            [
                can.manage_members,
                { title: 'Usuarios', href: members(slug), icon: UserCog },
                'admin',
            ],
            [
                can.manage_members,
                { title: 'Roles', href: roles(slug), icon: IdCard },
                'admin',
            ],
            [
                can.manage_members,
                { title: 'Plan y uso', href: plan(slug), icon: Layers },
                'admin',
            ],
            // The minimum per group of the anonymous tools (criterio C-23): a union has none of them.
            [
                can.manage_members && !union,
                { title: 'Privacidad', href: privacy(slug), icon: ShieldCheck },
                'admin',
            ],
        ];

    return pages
        .filter(([visible, , own]) => visible && own === group)
        .map(([, item]) => item);
};

/**
 * The pages of the panel, as the sidebar and the ⌘K palette list them (board 40, V4-Menu-Roles). Panel is the current
 * company's, or /admin without one; the company's own pages appear only inside a company, and only those its role may
 * open (by its permissions, never by its name). A superadmin outside a company gets none: /admin sends them to Empresas,
 * which is their start. Whoever only handles complaints (the outside handler) sees its cases and nothing else of the company.
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

    if (handlesCasesOnly(tenant)) {
        return hasModule(tenant, 'complaints')
            ? complaintCasesItems(tenant)
            : [];
    }

    return companyItems(tenant, 'daily');
};

/**
 * The company's account pages (who has access, with which roles, the plan and the privacy of results): they are rarely
 * the day's work, so the sidebar folds them under «Administración». They come right after the daily pages, so the menu's
 * order (menu-order.json, which StartRoute walks) is the same as when they were all in one list.
 */
export const adminNavItems = (tenant: NavTenant | null): NavItem[] =>
    tenant === null || handlesCasesOnly(tenant)
        ? []
        : companyItems(tenant, 'admin');

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

/**
 * The sidebar's groups: the company's daily pages (no heading), the three families of tools, the company's
 * administration, and for Humetría staff the sales desk and the platform.
 */
export type NavSectionKey =
    | 'main'
    | 'compliance'
    | 'listening'
    | 'operations'
    | 'admin'
    | 'console'
    | 'sales'
    | 'platform';

export type NavSection = {
    key?: NavSectionKey;
    label: string;
    items: NavItem[];
};

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
