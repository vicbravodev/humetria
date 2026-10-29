import { Building2, Factory, House, Inbox } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import { edit as editProfile } from '@/routes/profile';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSecurity } from '@/routes/security';
import { dashboard as tenantDashboard } from '@/routes/tenant';
import { index as workCenters } from '@/routes/tenant/work-centers';
import type { NavItem } from '@/types';

/**
 * The pages of the panel, as the sidebar and the ⌘K palette list them. Panel is the current company's, or /admin
 * without one; the company's own pages appear only inside a company.
 */
export const mainNavItems = (tenantSlug: string | null): NavItem[] =>
    tenantSlug === null
        ? [{ title: 'Panel', href: dashboard(), icon: House }]
        : [
              {
                  title: 'Panel',
                  href: tenantDashboard(tenantSlug),
                  icon: House,
              },
              {
                  title: 'Centros de trabajo',
                  href: workCenters(tenantSlug),
                  icon: Factory,
              },
          ];

// NOM-035, NOM-037 and Ley Silla enter here when each gets its first page.
export const complianceNavItems: NavItem[] = [];

// Platform tools for Humetría staff; the server answers 404 to anyone else anyway.
export const superadminNavItems: NavItem[] = [
    { title: 'Empresas', href: companies(), icon: Building2 },
    { title: 'Solicitudes de demo', href: demoRequests(), icon: Inbox },
];

const appearanceItem: NavItem = {
    title: 'Apariencia',
    href: editAppearance(),
    icon: null,
};

/** The settings pages; while impersonating only Apariencia, since the owner's own account is off limits. */
export const settingsNavItems = (impersonating: boolean): NavItem[] =>
    impersonating
        ? [appearanceItem]
        : [
              { title: 'Perfil', href: editProfile(), icon: null },
              { title: 'Seguridad', href: editSecurity(), icon: null },
              appearanceItem,
          ];

/** Where "Configuración" in the user menu lands. */
export const settingsEntryHref = (impersonating: boolean): NavItem['href'] =>
    impersonating ? editAppearance() : editProfile();
