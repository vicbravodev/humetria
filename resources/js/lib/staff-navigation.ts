import {
    Activity,
    Building2,
    ChartNoAxesColumn,
    Inbox,
    Presentation,
    ReceiptText,
    ScrollText,
    UserCog,
    WalletCards,
} from 'lucide-react';
import { index as audit } from '@/routes/audit';
import { index as accounts } from '@/routes/billing/accounts';
import { index as plans } from '@/routes/billing/plans';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import { show as demoRoom } from '@/routes/demo-room';
import { index as overview } from '@/routes/overview';
import { index as users } from '@/routes/platform-users';
import type { NavItem } from '@/types';

// Where the superadmin starts: the overview. Support has none.
export const homeNavItems = (isSuperadmin: boolean): NavItem[] =>
    isSuperadmin
        ? [{ title: 'Resumen', href: overview(), icon: ChartNoAxesColumn }]
        : [];

// The customers, for Humetría staff (the server answers 404 to anyone else anyway): who they are and who pays.
export const clientNavItems = (): NavItem[] => [
    { title: 'Empresas', href: companies(), icon: Building2 },
    { title: 'Cuentas y cobranza', href: accounts(), icon: WalletCards },
];

// Staff inside a company reach the whole console from one group.
export const consoleNavItems = (isSuperadmin: boolean): NavItem[] => [
    ...homeNavItems(isSuperadmin),
    ...clientNavItems(),
];

// The sales desk: the demo requests (support too) and the demo room, which is the superadmin's alone.
export const salesNavItems = (isSuperadmin: boolean): NavItem[] => [
    { title: 'Solicitudes de demo', href: demoRequests(), icon: Inbox },
    ...(isSuperadmin
        ? [{ title: 'Sala de demo', href: demoRoom(), icon: Presentation }]
        : []),
];

// The platform itself: its people, its audit trail, its catalog of plans and its queues are the superadmin's alone, support does not see them.
export const platformNavItems = (isSuperadmin: boolean): NavItem[] =>
    isSuperadmin
        ? [
              { title: 'Equipo Humetría', href: users(), icon: UserCog },
              { title: 'Bitácora', href: audit(), icon: ScrollText },
              { title: 'Planes y precios', href: plans(), icon: ReceiptText },
              // Horizon's own dashboard, outside the panel: a plain address, not an Inertia visit.
              {
                  title: 'Colas',
                  href: '/horizon',
                  icon: Activity,
                  external: true,
              },
          ]
        : [];
