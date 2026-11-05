import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import NoticeList from '@/components/telework/notice-list';
import NoticeToolbar from '@/components/telework/notice-toolbar';
import type { NoticeFilters, NoticeRow, Paginated } from '@/types';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { teleworkCrumbs } from '@/lib/telework/crumbs';

export default function Notices({
    notices,
    filters,
}: {
    notices: Paginated<NoticeRow>;
    filters: NoticeFilters;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: teleworkCrumbs(tenantSlug, null, { title: 'Avisos' }),
    });

    return (
        <>
            <Head title="Avisos de teletrabajo" />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Avisos de teletrabajo"
                    description="Lo que las personas avisan por escrito: cambios de domicilio (6.7), alteraciones de condiciones o fuerza mayor (6.3) y riesgos de trabajo (6.9). Revisa cada uno y anota qué se hará."
                />
                <NoticeToolbar filters={filters} />
                <NoticeList notices={notices} />
            </div>
        </>
    );
}
