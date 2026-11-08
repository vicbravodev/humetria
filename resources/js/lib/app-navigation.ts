import {
    Building2,
    Clock,
    ClipboardCheck,
    Factory,
    House,
    Laptop,
    Armchair,
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
import { index as attendanceIndex } from '@/routes/tenant/attendance';
import { index as attendanceCorrections } from '@/routes/tenant/attendance/corrections';
import { index as attendanceDevices } from '@/routes/tenant/attendance/devices';
import { report as attendanceReport } from '@/routes/tenant/attendance';
import { show as attendancePolicy } from '@/routes/tenant/attendance/policy';
import { index as attendanceSchedules } from '@/routes/tenant/attendance/schedules';
import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as disclosure } from '@/routes/tenant/disclosure';
import { index as employees } from '@/routes/tenant/employees';
import { index as programs } from '@/routes/tenant/interventions';
import { index as members } from '@/routes/tenant/members';
import { index as seatingIndex } from '@/routes/tenant/seating';
import { index as teleworkIndex } from '@/routes/tenant/telework';
import { index as teleworkDocuments } from '@/routes/tenant/telework/documents';
import { index as teleworkNotices } from '@/routes/tenant/telework/notices';
import { edit as teleworkSettings } from '@/routes/tenant/telework/settings';
import { index as teleworkTrainings } from '@/routes/tenant/telework/trainings';
import { index as workCenters } from '@/routes/tenant/work-centers';
import {
    complaintCasesItems,
    complaints,
    handlesOrManagesComplaints,
} from '@/lib/complaints-navigation';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem, InCourse, NavItem, NavTenant } from '@/types';

export type { NavTenant } from '@/types';

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
    isSuperadmin: boolean,
): NavItem[] => {
    if (tenant === null) {
        return isSuperadmin
            ? []
            : [{ title: 'Panel', href: dashboard(), icon: House }];
    }

    if (tenant.role === 'committee') {
        return [];
    }

    return tenant.role === 'complaints'
        ? complaintCasesItems(tenant)
        : companyItems(tenant);
};

/** NOM-037's own pages, under its entry: only those the role may open (the server answers 403 to the rest anyway). */
const telework = (tenant: NavTenant): NavItem => ({
    title: 'NOM-037',
    href: teleworkIndex(tenant.slug),
    icon: Laptop,
    children: [
        { title: 'Centros', href: teleworkIndex(tenant.slug) },
        { title: 'Documentos', href: teleworkDocuments(tenant.slug) },
        ...(tenant.can.manage_telework
            ? [{ title: 'Capacitación', href: teleworkTrainings(tenant.slug) }]
            : []),
        ...(tenant.can.review_workplaces
            ? [{ title: 'Avisos', href: teleworkNotices(tenant.slug) }]
            : []),
        ...(tenant.can.manage_telework
            ? [
                  {
                      title: 'Configuración',
                      href: teleworkSettings(tenant.slug),
                  },
              ]
            : []),
    ],
});

/** Ley Silla's entry: one link, the centers; its tabs live on each center's page. */
const seating = (tenant: NavTenant): NavItem => ({
    title: 'Ley Silla',
    href: seatingIndex(tenant.slug),
    icon: Armchair,
});

/**
 * The registro de jornada's entry: the week's overview (its centers hang from it), the people's correction requests, their
 * devices and schedules, the company's policy and, for whoever may manage it, the report for the authority.
 */
const attendance = (tenant: NavTenant): NavItem => ({
    title: 'Asistencia',
    href: attendanceIndex(tenant.slug),
    icon: Clock,
    children: [
        { title: 'Resumen', href: attendanceIndex(tenant.slug) },
        { title: 'Correcciones', href: attendanceCorrections(tenant.slug) },
        { title: 'Dispositivos', href: attendanceDevices(tenant.slug) },
        { title: 'Horarios', href: attendanceSchedules(tenant.slug) },
        { title: 'Política', href: attendancePolicy(tenant.slug) },
        ...(tenant.can.manage_attendance
            ? [
                  {
                      title: 'Reporte para la autoridad',
                      href: attendanceReport(tenant.slug),
                  },
              ]
            : []),
    ],
});

/**
 * NOM-035's entry: the evaluations, the Programas de intervención (num. 8.3 to 8.5) and the policy and its disclosure (5.1,
 * 5.7), for whoever may read each; with only the evaluations it stays a plain link.
 */
const nom035 = (tenant: NavTenant, inCourse: number): NavItem => {
    const children: NavItem[] = [
        { title: 'Evaluaciones', href: campaigns(tenant.slug) },
        ...(tenant.can.view_interventions
            ? [{ title: 'Programas', href: programs(tenant.slug) }]
            : []),
        ...(tenant.can.view_disclosure
            ? [
                  {
                      title: 'Política y difusión',
                      href: disclosure(tenant.slug),
                  },
              ]
            : []),
    ];

    return {
        title: 'NOM-035',
        href: campaigns(tenant.slug),
        icon: ClipboardCheck,
        badge: inCourse,
        ...(children.length > 1 ? { children } : {}),
    };
};

/**
 * The compliance tools of the current company, for every role: who may change them is the page's to say. The committee
 * sees only NOM-037 and Ley Silla; the outside handler of complaints has its cases in the main section instead.
 */
export const complianceNavItems = (
    tenant: NavTenant | null,
    inCourse = 0,
): NavItem[] => {
    if (tenant === null || isUnion(tenant) || tenant.role === 'complaints') {
        return [];
    }

    return [
        ...(tenant.role === 'committee' ? [] : [nom035(tenant, inCourse)]),
        telework(tenant),
        ...(tenant.can.view_seating ? [seating(tenant)] : []),
        ...(tenant.can.view_attendance ? [attendance(tenant)] : []),
        ...(handlesOrManagesComplaints(tenant) ? [complaints(tenant)] : []),
    ];
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
