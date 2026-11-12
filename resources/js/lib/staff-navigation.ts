import { Building2, Inbox, WalletCards } from 'lucide-react';
import { index as accounts } from '@/routes/billing/accounts';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import type { NavItem } from '@/types';

// Platform tools for Humetría staff; the server answers 404 to anyone else anyway.
export const superadminNavItems: NavItem[] = [
    { title: 'Empresas', href: companies(), icon: Building2 },
    { title: 'Cuentas', href: accounts(), icon: WalletCards },
    { title: 'Solicitudes de demo', href: demoRequests(), icon: Inbox },
];
