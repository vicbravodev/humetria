import { Head, setLayoutProps } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import PageLinkActions from '@/components/disclosure/page-link-actions';
import PageLinkCard from '@/components/disclosure/page-link-card';
import PageLinkStart from '@/components/disclosure/page-link-start';
import PageHeader from '@/components/humetria/page-header';
import type { PageLinkProps } from '@/types';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';

export default function Page({ link }: PageLinkProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, {
            title: 'Página de las personas',
        }),
    });

    return (
        <>
            <Head title="Página de las personas" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="NOM-035 · Difusión"
                    title="Página de las personas"
                    description="Un enlace permanente, para el cartel y el correo, donde las personas ven la política, las medidas, cómo presentar una queja y los resultados (numerales 5.7 y 7.8). Solo lee: no pide cuenta ni guarda quién entra."
                />
                {link === null ? (
                    <PageLinkStart icon={Globe} />
                ) : (
                    <>
                        <PageLinkCard link={link} />
                        <PageLinkActions link={link} />
                    </>
                )}
            </div>
        </>
    );
}
