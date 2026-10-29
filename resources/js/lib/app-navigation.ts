import { Building2, House, Inbox } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import { dashboard as tenantDashboard } from '@/routes/tenant';
import type { NavItem } from '@/types';

/** The pages of the panel, as the sidebar and the ⌘K palette list them; Panel is the current company's, or /admin without one. */
export const mainNavItems = (tenantSlug: string | null): NavItem[] => [
    {
        title: 'Panel',
        href: tenantSlug === null ? dashboard() : tenantDashboard(tenantSlug),
        icon: House,
    },
];

// NOM-035, NOM-037 and Ley Silla enter here when each gets its first page.
export const complianceNavItems: NavItem[] = [];

// Platform tools for Humetría staff; the server answers 404 to anyone else anyway.
export const superadminNavItems: NavItem[] = [
    { title: 'Empresas', href: companies(), icon: Building2 },
    { title: 'Solicitudes de demo', href: demoRequests(), icon: Inbox },
];
