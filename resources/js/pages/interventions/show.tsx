import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ProgramRows from '@/components/interventions/program-rows';
import ProgramStart from '@/components/interventions/program-start';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { programTrail } from '@/lib/interventions';
import type { ProgramCampaign, ProgramData, ProgramRequirement } from '@/types';

export default function ProgramShow({
    campaign,
    program,
    requirement,
    can,
}: {
    campaign: ProgramCampaign;
    program: ProgramData | null;
    requirement: ProgramRequirement | null;
    can: { manage: boolean };
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({ breadcrumbs: programTrail(tenantSlug, campaign) });

    return (
        <>
            <Head title={`Programa de intervención · ${campaign.workCenter}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={campaign.workCenter}
                    title="Programa de intervención"
                />
                {program === null && requirement !== null ? (
                    <ProgramStart
                        campaignId={campaign.id}
                        requirement={requirement}
                        canManage={can.manage}
                    />
                ) : (
                    <ProgramRows rows={program?.items ?? []} />
                )}
            </div>
        </>
    );
}
