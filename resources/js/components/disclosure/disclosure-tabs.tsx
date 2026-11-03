import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/disclosure';
import { policy } from '@/routes/tenant/disclosure';

export type DisclosureTab = 'board' | 'policy';

/**
 * The sections of «Política y difusión», each its own page and address (pantalla Eval-Activa, tabs): the board and the
 * policy. Each is a link, so the section lives in the URL.
 */
export default function DisclosureTabs({ active }: { active: DisclosureTab }) {
    const tenantSlug = useTenantSlug();

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
            ]}
        />
    );
}
