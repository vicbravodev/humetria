import { Head, setLayoutProps } from '@inertiajs/react';
import SurveyStatusBadge from '@/components/engagement/survey-status-badge';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { deadlineText } from '@/lib/engagement/panel';
import { index } from '@/routes/tenant/climate';
import type { ClimateSurveyCard } from '@/types';

/** One climate survey in its state: draft, in progress or closed (pantallas Eval-Borrador, Eval-Activa, Eval-Cerrada). */
export default function EngagementShow({
    survey,
}: {
    survey: ClimateSurveyCard;
    canManage: boolean;
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
                    {...(deadline === undefined
                        ? {}
                        : { description: deadline })}
                />
            </div>
        </>
    );
}
