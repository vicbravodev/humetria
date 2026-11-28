import {
    Activity,
    Building2,
    ChartNoAxesColumn,
    HeartPulse,
    Inbox,
    Megaphone,
    MessagesSquare,
    Presentation,
    ReceiptText,
    ScrollText,
    UserCog,
    WalletCards,
} from 'lucide-react';
import { index as announcements } from '@/routes/announcements';
import { index as audit } from '@/routes/audit';
import { index as accounts } from '@/routes/billing/accounts';
import { index as plans } from '@/routes/billing/plans';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import { health, messaging } from '@/routes/operations';
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
    // The desk opens on the funnel; the list stays one tab away.
    {
        title: 'Solicitudes de demo',
        href: demoRequests({ query: { view: 'board' } }),
        icon: Inbox,
    },
    ...(isSuperadmin
        ? [{ title: 'Sala de demo', href: demoRoom(), icon: Presentation }]
        : []),
];

// The platform itself: its people, its audit trail, its health, its messaging, its catalog of plans and its queues are the superadmin's alone, support does not see them.
export const platformNavItems = (isSuperadmin: boolean): NavItem[] =>
    isSuperadmin
        ? [
              { title: 'Equipo Humetría', href: users(), icon: UserCog },
              { title: 'Bitácora', href: audit(), icon: ScrollText },
              { title: 'Salud del sistema', href: health(), icon: HeartPulse },
              { title: 'Mensajería', href: messaging(), icon: MessagesSquare },
              { title: 'Planes y precios', href: plans(), icon: ReceiptText },
              { title: 'Avisos', href: announcements(), icon: Megaphone },
              // Horizon's own dashboard, outside the panel: a plain address, not an Inertia visit.
              {
                  title: 'Colas',
                  href: '/horizon',
                  icon: Activity,
                  external: true,
              },
          ]
        : [];
