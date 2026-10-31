import LaunchForm from '@/components/campaigns/launch-form';
import SampleSection from '@/components/campaigns/sample-section';
import type {
    CampaignDetail,
    DraftContact,
    Paginated,
    SampledPerson,
} from '@/types';

/** A draft for those who manage it (pantalla Eval-Borrador): the three steps to launch it, and the sample it drew. */
export default function DraftReview({
    route,
    company,
    campaign,
    draft,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    company: string;
    campaign: CampaignDetail;
    draft: {
        launch: { min: string; suggested: string } | null;
        contact: DraftContact | null;
        sample: Paginated<SampledPerson> | null;
    };
}) {
    return (
        <>
            {draft.launch !== null && draft.contact !== null && (
                <LaunchForm
                    tenant={{ slug: route[0], name: company }}
                    campaign={campaign}
                    limits={draft.launch}
                    contact={draft.contact}
                />
            )}
            {draft.sample !== null && (
                <SampleSection route={route} sample={draft.sample} />
            )}
        </>
    );
}
