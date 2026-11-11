import { Head, Link } from '@inertiajs/react';
import { Network, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import OrgQualityEmpty from '@/components/organization/org-quality-empty';
import OrgQualityTable from '@/components/organization/org-quality-table';
import OrgQualityTabs from '@/components/organization/org-quality-tabs';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { qualityHeadline, qualityPending } from '@/lib/org-chart/quality';
import { index } from '@/routes/tenant/org-chart';
import type { Paginated, QualityPerson, ReportingIssue } from '@/types';

export default function OrgChartQuality({
    issues,
    issue,
    people,
    can_manage,
}: {
    issues: ReportingIssue[];
    issue: ReportingIssue['value'];
    people: Paginated<QualityPerson>;
    can_manage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const complete = qualityPending(issues) === 0;

    return (
        <>
            <Head title="Calidad de datos" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Organigrama"
                    title="Calidad de datos"
                    description={qualityHeadline(issues)}
                >
                    {!complete && (
                        <Button variant="outline" asChild>
                            <Link href={index(tenantSlug)}>
                                <Network />
                                Ver organigrama
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {complete ? (
                    <OrgQualityEmpty />
                ) : (
                    <>
                        <OrgQualityTabs issues={issues} current={issue} />
                        {people.data.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="Nadie en esta lista"
                            >
                                Revisa las otras pestañas: ahí está lo que falta
                                por corregir.
                            </EmptyState>
                        ) : (
                            <OrgQualityTable
                                people={people.data}
                                canManage={can_manage}
                            />
                        )}
                        <Pagination
                            page={people}
                            label="Páginas de personas por revisar"
                        />
                    </>
                )}
            </div>
        </>
    );
}
