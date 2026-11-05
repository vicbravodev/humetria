import { Head, setLayoutProps } from '@inertiajs/react';
import ContentCard from '@/components/disclosure/content-card';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import type { ContentsProps } from '@/types';

export default function Contents({ blocks }: ContentsProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, {
            title: 'Contenidos para las personas',
        }),
    });

    return (
        <>
            <Head title="Contenidos para las personas" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="NOM-035 · numeral 5.7"
                    title="Contenidos para las personas"
                    description="Además de la política, el numeral 5.7 pide informar sobre las medidas, cómo presentar una queja y las posibles alteraciones a la salud. Escribe cada uno con preguntas: las personas lo leen en su página."
                />
                {blocks.map((block) => (
                    <ContentCard key={block.kind} block={block} />
                ))}
            </div>
        </>
    );
}
