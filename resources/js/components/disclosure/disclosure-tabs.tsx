import { usePage } from '@inertiajs/react';
import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    contents,
    dissemination,
    index,
    page,
    policy,
    results,
} from '@/routes/tenant/disclosure';

export type DisclosureTab =
    | 'board'
    | 'policy'
    | 'dissemination'
    | 'contents'
    | 'results'
    | 'page';

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
                              label: 'Contenidos',
                              href: contents(tenantSlug),
                              active: active === 'contents',
                          },
                          {
                              label: 'Resultados',
                              href: results(tenantSlug),
                              active: active === 'results',
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
