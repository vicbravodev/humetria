import { usePage } from '@inertiajs/react';
import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dissemination, index, page, policy } from '@/routes/tenant/disclosure';

export type DisclosureTab = 'board' | 'policy' | 'dissemination' | 'page';

/**
 * The sections of «Política y difusión», each its own page and address (pantalla Eval-Activa, tabs): the board and the
 * policy and, for whoever manages it, the disclosure. Each is a link, so the section lives in the URL.
 */
export default function DisclosureTabs({ active }: { active: DisclosureTab }) {
    const tenantSlug = useTenantSlug();
    const manage = usePage().props.tenant?.can.manage_disclosure === true;

    return (
        <CountTabs
            label="Secciones de política y difusión"
            tabs={[
                {
                    label: 'Resumen',
                    href: index(tenantSlug),
                    active: active === 'board',
                },
                {
                    label: 'Política',
                    href: policy(tenantSlug),
                    active: active === 'policy',
                },
                // The follow-up names people: only whoever manages sees it.
                ...(manage
                    ? [
                          {
                              label: 'Difusión',
                              href: dissemination(tenantSlug),
                              active: active === 'dissemination',
                          },
                          {
                              label: 'Página',
                              href: page(tenantSlug),
                              active: active === 'page',
                          },
                      ]
                    : []),
            ]}
        />
    );
}
