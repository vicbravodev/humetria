import { Activity, Calculator, Gauge, Network } from 'lucide-react';

import type { NavTenant } from '@/lib/app-navigation';
import { index as climate } from '@/routes/tenant/climate';
import { index as compensation } from '@/routes/tenant/compensation';
import { index as orgChart, quality } from '@/routes/tenant/org-chart';
import { index as pulse } from '@/routes/tenant/pulse';
import type { NavItem } from '@/types';

/**
 * The tools that are not compliance, for whoever may view them: the org chart and Clima, in a company only (a union's
 * roster has no hierarchy), and Compensación, in a union as well as in a company (the server keeps them from anyone else).
 */
export const toolNavItems = (tenant: NavTenant | null): NavItem[] =>
    tenant === null
        ? []
        : [
              ...(tenant.can.view_org_chart && tenant.type !== 'union'
                  ? [
                        {
                            title: 'Organigrama',
                            href: orgChart(tenant.slug),
                            icon: Network,
                            children: [
                                {
                                    title: 'Organigrama',
                                    href: orgChart(tenant.slug),
                                },
                                {
                                    title: 'Calidad de datos',
                                    href: quality(tenant.slug),
                                },
                            ],
                        },
                    ]
                  : []),
              ...(tenant.can.view_climate && tenant.type !== 'union'
                  ? [
                        {
                            title: 'Clima',
                            href: climate(tenant.slug),
                            icon: Gauge,
                        },
                    ]
                  : []),
              ...(tenant.can.view_pulse && tenant.type !== 'union'
                  ? [
                        {
                            title: 'Pulso',
                            href: pulse(tenant.slug),
                            icon: Activity,
                        },
                    ]
                  : []),
              ...(tenant.can.view_compensation
                  ? [
                        {
                            title: 'Compensación',
                            href: compensation(tenant.slug),
                            icon: Calculator,
                        },
                    ]
                  : []),
          ];
