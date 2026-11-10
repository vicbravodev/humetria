import { Head, setLayoutProps } from '@inertiajs/react';
import DeleteDraftDialog from '@/components/engagement/delete-draft-dialog';
import DraftReview from '@/components/engagement/draft-review';
import SurveyStatusBadge from '@/components/engagement/survey-status-badge';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { deadlineText } from '@/lib/engagement/panel';
import { index } from '@/routes/tenant/climate';
import type {
    ClimateDraft,
    ClimateSection,
    ClimateSurveyCard,
    ClimateUnreachable,
    Paginated,
} from '@/types';

/** One climate survey in its state: draft, in progress or closed (pantallas Eval-Borrador, Eval-Activa, Eval-Cerrada). */
export default function EngagementShow({
    survey,
    canManage,
    draft,
    unreachable,
    sections,
}: {
    survey: ClimateSurveyCard;
    canManage: boolean;
    draft: ClimateDraft | null;
    unreachable: Paginated<ClimateUnreachable> | null;
    sections: ClimateSection[];
}) {
    const tenantSlug = useTenantSlug();
    const deadline = deadlineText(survey);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clima', href: index(tenantSlug) },
            { title: survey.name },
        ],
    });

    return (
        <>
            <Head title={survey.name} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title={survey.name}
                    status={
                        <SurveyStatusBadge
                            status={survey.status}
                            label={survey.statusLabel}
                        />
                    }
                    description={
                        survey.status === 'draft'
                            ? canManage
                                ? 'Revisa tres cosas y lanza. Nadie recibe nada hasta que lo hagas.'
                                : 'Todavía no se lanza: nadie ha recibido nada.'
                            : deadline
                    }
                >
                    {survey.status === 'draft' && canManage && (
                        <DeleteDraftDialog surveyId={survey.id} />
                    )}
                </PageHeader>
                {draft !== null && unreachable !== null && (
                    <DraftReview
                        surveyId={survey.id}
                        draft={draft}
                        unreachable={unreachable}
                        sections={sections}
                    />
                )}
            </div>
        </>
    );
}
