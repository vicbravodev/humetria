import {
    Building2,
    ChartNoAxesColumn,
    Inbox,
    ScrollText,
    UserCog,
    WalletCards,
} from 'lucide-react';
import { index as audit } from '@/routes/audit';
import { index as accounts } from '@/routes/billing/accounts';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import { index as overview } from '@/routes/overview';
import { index as users } from '@/routes/platform-users';
import type { NavItem } from '@/types';

// Platform tools for Humetría staff; the server answers 404 to anyone else anyway.
const staffNavItems: NavItem[] = [
    { title: 'Empresas', href: companies(), icon: Building2 },
    { title: 'Cuentas', href: accounts(), icon: WalletCards },
    { title: 'Solicitudes de demo', href: demoRequests(), icon: Inbox },
];

// The platform's people and its audit trail are the superadmin's alone: support does not see them in the menu.
const superadminOnlyNavItems: NavItem[] = [
    { title: 'Usuarios', href: users(), icon: UserCog },
    { title: 'Bitácora', href: audit(), icon: ScrollText },
];

export function platformNavItems(isSuperadmin: boolean): NavItem[] {
    return isSuperadmin
        ? [
              { title: 'Resumen', href: overview(), icon: ChartNoAxesColumn },
              ...staffNavItems,
              ...superadminOnlyNavItems,
          ]
        : staffNavItems;
}
