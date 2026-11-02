import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ReviewAnswersView from '@/components/telework/review-answers';
import ReviewEvidence from '@/components/telework/review-evidence';
import ReviewSide from '@/components/telework/review-side';
import ReviewVisits from '@/components/telework/review-visits';
import WorkplaceStatusBadge from '@/components/telework/workplace-status-badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reviewFacts } from '@/lib/telework/review';
import { index } from '@/routes/tenant/telework/reviews';
import type { ReviewDetail } from '@/types';

export default function Review({
    workCenter,
    review,
}: {
    workCenter: { id: string; name: string };
    review: ReviewDetail;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-037' },
            {
                title: 'Listas por revisar',
                href: index({ tenant: tenantSlug, workCenter: workCenter.id }),
            },
            { title: review.person.name },
        ],
    });

    return (
        <>
            <Head title={`Revisión · ${review.person.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption={workCenter.name}
                    title="Lista de verificación"
                    status={
                        <WorkplaceStatusBadge
                            status={review.workplace.status}
                            label={review.workplace.statusLabel}
                        />
                    }
                    facts={reviewFacts(review)}
                />
                <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
                    <div className="flex flex-col gap-6">
                        <ReviewAnswersView
                            answers={review.answers}
                            specialNeeds={review.application.specialNeeds}
                        />
                        <ReviewEvidence evidence={review.evidence} />
                        <ReviewVisits
                            workplaceId={review.workplace.id}
                            authorizations={review.authorizations}
                            canAsk={review.workplace.status !== 'retired'}
                        />
                    </div>
                    <ReviewSide review={review} />
                </div>
            </div>
        </>
    );
}
