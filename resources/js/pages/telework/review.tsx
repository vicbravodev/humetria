import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { CalendarClock, MapPin, User } from 'lucide-react';
import PageHeader from '@/components/page-header';
import ReviewAnswersView from '@/components/telework/review-answers';
import ReviewEvidence from '@/components/telework/review-evidence';
import ReviewSide from '@/components/telework/review-side';
import ReviewVisits from '@/components/telework/review-visits';
import WorkplaceStatusBadge from '@/components/telework/workplace-status-badge';
import { describeDate } from '@/lib/calendar-date';
import { index } from '@/routes/tenant/telework/reviews';
import type { PageFact } from '@/components/page-header';
import type { ReviewDetail } from '@/types';

function reviewFacts(review: ReviewDetail): PageFact[] {
    const expires =
        review.workplace.expiresOn === null
            ? undefined
            : describeDate(review.workplace.expiresOn);

    return [
        {
            icon: User,
            text: `${review.person.name} · ${review.person.jobTitle}`,
        },
        { icon: MapPin, text: review.workplace.address },
        ...(expires === undefined
            ? []
            : [{ icon: CalendarClock, text: `Vigente hasta ${expires}` }]),
    ];
}

export default function Review({
    workCenter,
    review,
}: {
    workCenter: { id: string; name: string };
    review: ReviewDetail;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
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
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
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
                            tenantSlug={tenantSlug}
                            answers={review.answers}
                            specialNeeds={review.application.specialNeeds}
                        />
                        <ReviewEvidence
                            tenantSlug={tenantSlug}
                            evidence={review.evidence}
                        />
                        <ReviewVisits
                            tenantSlug={tenantSlug}
                            workplaceId={review.workplace.id}
                            authorizations={review.authorizations}
                            canAsk={review.workplace.status !== 'retired'}
                        />
                    </div>
                    <ReviewSide tenantSlug={tenantSlug} review={review} />
                </div>
            </div>
        </>
    );
}
