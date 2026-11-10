import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import type { OrgChartFilters, OrgNode, Paginated } from '@/types';

export default function OrgChart(_props: {
    roots: Paginated<OrgNode>;
    chain: OrgNode[];
    focus: { id: string; children: Paginated<OrgNode> } | null;
    filters: OrgChartFilters;
    total_people: number;
    with_manager: number;
    can_manage: boolean;
}) {
    return (
        <>
            <Head title="Organigrama" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title="Organigrama" />
            </div>
        </>
    );
}
