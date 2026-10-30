import ActiveCampaignActions from '@/components/campaigns/active-campaign-actions';
import InstrumentsProgress from '@/components/campaigns/instruments-progress';
import PostButton from '@/components/campaigns/post-button';
import ResendFilteredDialog from '@/components/campaigns/resend-filtered-dialog';
import PeopleTable from '@/components/campaigns/people-table';
import PeopleToolbar from '@/components/campaigns/people-toolbar';
import ConsolePagination from '@/components/console-pagination';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { progressNote } from '@/lib/campaigns';
import { show } from '@/routes/tenant/campaigns';
import { resend } from '@/routes/tenant/campaigns/participants';
import type {
    CampaignDetail,
    CampaignPerson,
    InstrumentProgress,
    PeoplePage,
    ReplacementPool,
} from '@/types';

/**
 * A launched campaign: how many answered each guide, whether the Guía III sample is met (III.2 c), and, for those who
 * manage it, who answered.
 */
export default function CampaignFollowUp({
    tenantSlug,
    campaign,
    instruments,
    people,
    minDeadline,
    replacementPool,
}: {
    tenantSlug: string;
    minDeadline: string | null;
    replacementPool: ReplacementPool[] | null;
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    people: PeoplePage | null;
}) {
    const progress = campaign.progress;
    const sampleShort =
        progress !== null &&
        progress.againstSample &&
        progress.completed < progress.target;

    const active = campaign.status === 'active' && people !== null;

    return (
        <>
            {active && minDeadline !== null && (
                <ActiveCampaignActions
                    tenantSlug={tenantSlug}
                    campaign={campaign}
                    minDeadline={minDeadline}
                    replacementPool={replacementPool}
                />
            )}
            {sampleShort && (
                <Alert>
                    <AlertDescription>
                        {progressNote(progress)} para el mínimo de la muestra
                        que pide la norma ({progress.target}). Si alguien no
                        contestará, sortea reemplazos.
                    </AlertDescription>
                </Alert>
            )}
            <InstrumentsProgress instruments={instruments} />
            {people !== null && (
                <section className="flex flex-col gap-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-xl leading-7 font-semibold">
                            Personas
                        </h2>
                        {active && (
                            <ResendFilteredDialog
                                tenantSlug={tenantSlug}
                                campaignId={campaign.id}
                                filters={people.filters}
                                count={people.people.total}
                            />
                        )}
                    </div>
                    <PeopleToolbar
                        url={show.url([tenantSlug, campaign.id])}
                        filters={people.filters}
                        standings={people.standings}
                        instruments={instruments}
                    />
                    <PeopleTable
                        people={people.people.data}
                        {...(active
                            ? {
                                  action: (person: CampaignPerson) =>
                                      person.reachable &&
                                      person.guides.some(
                                          (guide) => guide.status === 'pending',
                                      ) ? (
                                          <PostButton
                                              form={resend.form([
                                                  tenantSlug,
                                                  campaign.id,
                                                  person.id,
                                              ])}
                                              variant="ghost"
                                              size="sm"
                                          >
                                              Reenviar
                                          </PostButton>
                                      ) : null,
                              }
                            : {})}
                    />
                    <ConsolePagination
                        page={people.people}
                        label="Páginas de personas"
                        previous="Anteriores"
                        next="Siguientes"
                    />
                </section>
            )}
        </>
    );
}
