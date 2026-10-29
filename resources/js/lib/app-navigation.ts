import { Building2, House, Inbox } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import type { NavItem } from '@/types';

/** The pages of the panel, as the sidebar and the ⌘K palette list them. */
export const mainNavItems: NavItem[] = [
    { title: 'Panel', href: dashboard(), icon: House },
];

// Platform tools for Humetría staff; the server answers 404 to anyone else anyway.
export const superadminNavItems: NavItem[] = [
    { title: 'Empresas', href: companies(), icon: Building2 },
    { title: 'Solicitudes de demo', href: demoRequests(), icon: Inbox },
];
