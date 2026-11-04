import ProgramActions from '@/components/interventions/program-actions';
import ProgramFollowUp from '@/components/interventions/program-follow-up';
import ProgramHeader from '@/components/interventions/program-header';
import ProgramRows from '@/components/interventions/program-rows';
import ProgramSummary from '@/components/interventions/program-summary';
import ProgramTabs from '@/components/interventions/program-tabs';
import TableChecklist from '@/components/interventions/table-checklist';
import { useProgramTab } from '@/hooks/use-program-tab';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { programTools } from '@/lib/interventions';
import { program as programRoute } from '@/routes/tenant/campaigns';
import type { ProgramData, ProgramPageProps } from '@/types';

/**
 * A Programa that exists (pantalla Eval-Cerrada): its header, where it stands and what the Tabla asks, then its rows, its
 * actions and its follow-up evaluations as three views of the same page.
 */
export default function ProgramBody({
    page,
    program,
}: {
    page: ProgramPageProps;
    program: ProgramData;
}) {
    const tenantSlug = useTenantSlug();
    const tab = useProgramTab();
    const tools = programTools(page);
    const path = programRoute.url([tenantSlug, page.campaign.id]);
    const campaignId = page.campaign.id;

    return (
        <>
            <ProgramHeader
                campaign={page.campaign}
                program={program}
                members={page.members}
            />
            <div className="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <ProgramSummary program={program} />
                <TableChecklist requirements={program.requirements} />
            </div>
            <ProgramTabs
                path={path}
                counts={{
                    rows: program.items.length,
                    actions: page.actions?.total ?? 0,
                    followUp: program.items.filter(
                        (row) => row.followUp.dueOn !== null,
                    ).length,
                }}
            />
            {tab === 'rows' && (
                <ProgramRows
                    rows={program.items}
                    campaignId={campaignId}
                    tools={tools}
                />
            )}
            {tab === 'actions' && page.actions !== null && (
                <ProgramActions
                    url={path}
                    actions={page.actions}
                    rows={program.items}
                    filters={page.filters}
                    campaignId={campaignId}
                    tools={tools}
                />
            )}
            {tab === 'follow-up' && (
                <ProgramFollowUp
                    rows={program.items}
                    campaignId={campaignId}
                    tools={tools}
                />
            )}
        </>
    );
}
