import ActiveCampaignActions from '@/components/campaigns/active-campaign-actions';
import InstrumentsProgress from '@/components/campaigns/instruments-progress';
import PostButton from '@/components/campaigns/post-button';
import PeopleTable from '@/components/campaigns/people-table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { progressNote } from '@/lib/campaigns';
import { resend } from '@/routes/tenant/campaigns/participants';
import type {
    CampaignDetail,
    CampaignPerson,
    InstrumentProgress,
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
}: {
    tenantSlug: string;
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    people: CampaignPerson[] | null;
}) {
    const progress = campaign.progress;
    const sampleShort =
        progress !== null &&
        progress.againstSample &&
        progress.completed < progress.target;

    const active = campaign.status === 'active' && people !== null;

    return (
        <>
            {active && (
                <ActiveCampaignActions
                    tenantSlug={tenantSlug}
                    campaign={campaign}
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
                    <h2 className="text-xl leading-7 font-semibold">
                        Personas ({people.length})
                    </h2>
                    <PeopleTable
                        people={people}
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
                </section>
            )}
        </>
    );
}
