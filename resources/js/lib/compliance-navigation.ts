import { Armchair, ClipboardCheck, Clock, Laptop } from 'lucide-react';
import {
    complaints,
    handlesOrManagesComplaints,
} from '@/lib/complaints-navigation';
import { hasModule } from '@/lib/modules';
import {
    index as attendanceIndex,
    report as attendanceReport,
} from '@/routes/tenant/attendance';
import { index as attendanceCorrections } from '@/routes/tenant/attendance/corrections';
import { index as attendanceDevices } from '@/routes/tenant/attendance/devices';
import { show as attendancePolicy } from '@/routes/tenant/attendance/policy';
import { index as attendanceSchedules } from '@/routes/tenant/attendance/schedules';
import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as disclosure } from '@/routes/tenant/disclosure';
import { index as programs } from '@/routes/tenant/interventions';
import { index as seatingIndex } from '@/routes/tenant/seating';
import { index as teleworkIndex } from '@/routes/tenant/telework';
import { index as teleworkDocuments } from '@/routes/tenant/telework/documents';
import { index as teleworkNotices } from '@/routes/tenant/telework/notices';
import { edit as teleworkSettings } from '@/routes/tenant/telework/settings';
import { index as teleworkTrainings } from '@/routes/tenant/telework/trainings';
import type { NavItem, NavTenant } from '@/types';

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
 * The compliance tools of the current company, for every role: who may change them is the page's to say. Only those whose
 * module the company has. The committee sees only NOM-037 and Ley Silla; the outside handler of complaints has its cases in the
 * main section instead.
 */
export const complianceNavItems = (
    tenant: NavTenant | null,
    inCourse = 0,
): NavItem[] => {
    if (
        tenant === null ||
        tenant.type === 'union' ||
        tenant.role === 'complaints'
    ) {
        return [];
    }

    const tools: [module: string, visible: boolean, item: () => NavItem][] = [
        ['nom035', tenant.role !== 'committee', () => nom035(tenant, inCourse)],
        ['nom037', true, () => telework(tenant)],
        ['seating', tenant.can.view_seating, () => seating(tenant)],
        ['attendance', tenant.can.view_attendance, () => attendance(tenant)],
        [
            'complaints',
            handlesOrManagesComplaints(tenant),
            () => complaints(tenant),
        ],
    ];

    return tools
        .filter(([module, visible]) => visible && hasModule(tenant, module))
        .map(([, , item]) => item());
};
