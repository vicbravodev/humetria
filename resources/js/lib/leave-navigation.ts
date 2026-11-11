import { CalendarDays } from 'lucide-react';
import {
    calendar as vacationsCalendar,
    index as vacationsIndex,
} from '@/routes/tenant/vacations';
import { index as vacationsBalances } from '@/routes/tenant/vacations/balances';
import { show as vacationsImport } from '@/routes/tenant/vacations/import';
import { index as vacationsPolicies } from '@/routes/tenant/vacations/policies';
import { index as vacationsRequests } from '@/routes/tenant/vacations/requests';
import { show as vacationsSettings } from '@/routes/tenant/vacations/settings';
import type { NavItem, NavTenant } from '@/types';

/** The vacations' entry: the overview, the requests, the calendar, the days and the policies; the settings and the import only for whoever manages them. */
const vacations = (tenant: NavTenant): NavItem => ({
    title: 'Vacaciones',
    href: vacationsIndex(tenant.slug),
    icon: CalendarDays,
    children: [
        { title: 'Resumen', href: vacationsIndex(tenant.slug) },
        { title: 'Solicitudes', href: vacationsRequests(tenant.slug) },
        { title: 'Calendario', href: vacationsCalendar(tenant.slug) },
        { title: 'Saldos', href: vacationsBalances(tenant.slug) },
        { title: 'Políticas', href: vacationsPolicies(tenant.slug) },
        ...(tenant.can.manage_vacations
            ? [
                  {
                      title: 'Configuración',
                      href: vacationsSettings(tenant.slug),
                  },
                  { title: 'Importar', href: vacationsImport(tenant.slug) },
              ]
            : []),
    ],
});

/** The entry for whoever may view the vacations; nothing for the rest. */
export const vacationsNavItems = (tenant: NavTenant): NavItem[] =>
    tenant.can.view_vacations ? [vacations(tenant)] : [];
