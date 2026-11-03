import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ProgramBody from '@/components/interventions/program-body';
import ProgramStart from '@/components/interventions/program-start';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { programTrail } from '@/lib/interventions';
import type { ProgramPageProps } from '@/types';

export default function ProgramShow(props: ProgramPageProps) {
    const tenantSlug = useTenantSlug();
    const { campaign, program, requirement, can } = props;
    setLayoutProps({ breadcrumbs: programTrail(tenantSlug, campaign) });

    return (
        <>
            <Head title={`Programa de intervención · ${campaign.workCenter}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                {program !== null ? (
                    <ProgramBody page={props} program={program} />
                ) : (
                    <>
                        <PageHeader
                            caption={campaign.workCenter}
                            title="Programa de intervención"
                        />
                        {requirement !== null && (
                            <ProgramStart
                                campaignId={campaign.id}
                                requirement={requirement}
                                canManage={can.manage}
                            />
                        )}
                    </>
                )}
            </div>
        </>
    );
}
