import { Activity, Calculator, Gauge, Network } from 'lucide-react';

import {
    complaints,
    handlesOrManagesComplaints,
} from '@/lib/complaints-navigation';
import { vacationsNavItems } from '@/lib/leave-navigation';
import { hasModule } from '@/lib/modules';
import { index as climate } from '@/routes/tenant/climate';
import { index as compensation } from '@/routes/tenant/compensation';
import { index as orgChart, quality } from '@/routes/tenant/org-chart';
import { index as pulse } from '@/routes/tenant/pulse';
import type { NavItem, NavTenant } from '@/types';

/** A company's own people: not a union, nor the outside handler of complaints, whose cases are its whole menu. */
const companyStaff = (tenant: NavTenant): boolean =>
    tenant.type !== 'union' && tenant.role !== 'complaints';

/** A union's roster has no hierarchy, no climate and no pulse: those are a company's only. */
const inCompany = (tenant: NavTenant): boolean => tenant.type !== 'union';

/** A tool of a menu: the module it needs (none for Compensación), whether the role may open it, and its entries. */
type Tool = [module: string | null, visible: boolean, items: () => NavItem[]];

/** Whoever may open the tool, in a company that has its module. */
const available = (tenant: NavTenant, tools: Tool[]): NavItem[] =>
    tools
        .filter(
            ([module, visible]) =>
                visible && (module === null || hasModule(tenant, module)),
        )
        .flatMap(([, , items]) => items());

const orgChartItem = (tenant: NavTenant): NavItem => ({
    title: 'Organigrama',
    href: orgChart(tenant.slug),
    icon: Network,
    children: [
        { title: 'Organigrama', href: orgChart(tenant.slug) },
        { title: 'Lista', href: quality(tenant.slug) },
    ],
});

/**
 * "Escucha a tu gente": the complaints channel, Clima and Pulso, for whoever may open each in a company that has its
 * module (the server keeps them from anyone else).
 */
export const listeningNavItems = (tenant: NavTenant | null): NavItem[] =>
    tenant === null
        ? []
        : available(tenant, [
              [
                  'complaints',
                  companyStaff(tenant) && handlesOrManagesComplaints(tenant),
                  () => [complaints(tenant)],
              ],
              [
                  'climate',
                  tenant.can.view_climate && inCompany(tenant),
                  () => [
                      {
                          title: 'Clima',
                          href: climate(tenant.slug),
                          icon: Gauge,
                      },
                  ],
              ],
              [
                  'pulse',
                  tenant.can.view_pulse && inCompany(tenant),
                  () => [
                      {
                          title: 'Pulso',
                          href: pulse(tenant.slug),
                          icon: Activity,
                      },
                  ],
              ],
          ]);

/**
 * "Ordena tu operación": the vacations, the org chart and Compensación. Compensación is a union's too, for its side of
 * the table, and has no module; the rest are a company's and need theirs.
 */
export const operationsNavItems = (tenant: NavTenant | null): NavItem[] =>
    tenant === null
        ? []
        : available(tenant, [
              [
                  'vacations',
                  companyStaff(tenant),
                  () => vacationsNavItems(tenant),
              ],
              [
                  'org_chart',
                  tenant.can.view_org_chart && inCompany(tenant),
                  () => [orgChartItem(tenant)],
              ],
              [
                  null,
                  tenant.can.view_compensation,
                  () => [
                      {
                          title: 'Compensación',
                          href: compensation(tenant.slug),
                          icon: Calculator,
                      },
                  ],
              ],
          ]);
