import { Clock } from 'lucide-react';
import {
    index as attendanceIndex,
    report as attendanceReport,
} from '@/routes/tenant/attendance';
import { index as attendanceCorrections } from '@/routes/tenant/attendance/corrections';
import { index as attendanceDevices } from '@/routes/tenant/attendance/devices';
import { show as attendancePolicy } from '@/routes/tenant/attendance/policy';
import { index as attendanceSchedules } from '@/routes/tenant/attendance/schedules';
import type { NavItem, NavTenant } from '@/types';

/**
 * The registro de jornada's entry: the week's overview (its centers hang from it), the people's correction requests, their
 * devices and schedules, the company's policy and, for whoever may manage it, the report for the authority.
 */
export const attendance = (tenant: NavTenant): NavItem => ({
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
