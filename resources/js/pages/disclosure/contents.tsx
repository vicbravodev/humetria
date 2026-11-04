import { Head, setLayoutProps } from '@inertiajs/react';
import ContentCard from '@/components/disclosure/content-card';
import DisclosureTabs from '@/components/disclosure/disclosure-tabs';
import PageHeader from '@/components/humetria/page-header';
import type { ContentsProps } from '@/types';

export default function Contents({ blocks, centersInUse }: ContentsProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'NOM-035' }, { title: 'Política y difusión' }],
    });

    return (
        <>
            <Head title="Contenidos para las personas" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Política y difusión"
                    title="Contenidos para las personas"
                    description="Además de la política, el numeral 5.7 pide informar sobre las medidas, cómo presentar una queja y las posibles alteraciones a la salud. Escríbelo aquí: las personas lo leen en su página."
                />
                <DisclosureTabs active="contents" />
                {blocks.map((block) => (
                    <ContentCard
                        key={block.kind}
                        block={block}
                        centersInUse={centersInUse}
                    />
                ))}
            </div>
        </>
    );
}
