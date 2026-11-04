import { Calculator } from 'lucide-react';

import type { NavTenant } from '@/lib/app-navigation';
import { index as compensation } from '@/routes/tenant/compensation';
import type { NavItem } from '@/types';

/**
 * The tools that are not compliance, for whoever may view them: Compensación, in a union as well as in a company (the
 * server keeps it from anyone else).
 */
export const toolNavItems = (tenant: NavTenant | null): NavItem[] =>
    tenant?.can.view_compensation === true
        ? [
              {
                  title: 'Compensación',
                  href: compensation(tenant.slug),
                  icon: Calculator,
              },
          ]
        : [];
