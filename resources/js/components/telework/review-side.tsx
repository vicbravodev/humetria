import ReviewAskDialog from '@/components/telework/review-ask-dialog';
import ReviewDecisionCard from '@/components/telework/review-decision-card';
import ReviewDecisionForm from '@/components/telework/review-decision-form';
import ReviewRemoteCheckDialog from '@/components/telework/review-remote-check-dialog';
import { store } from '@/routes/tenant/telework/reviews';
import type { ReviewDetail } from '@/types';

/** What the Comisión can do with this list: decide it, or ask for more before deciding. */
export default function ReviewSide({
    tenantSlug,
    review,
}: {
    tenantSlug: string;
    review: ReviewDetail;
}) {
    const workplaceId = review.workplace.id;

    return (
        <aside className="flex flex-col gap-6">
            {review.current !== null && (
                <ReviewDecisionCard
                    title="Decisión de esta lista"
                    record={review.current}
                />
            )}
            {review.canDecide && (
                <section className="grid gap-4 rounded-xl border bg-card p-5">
                    <h2 className="text-lg font-semibold">Tu decisión</h2>
                    <ReviewDecisionForm
                        action={store.url({
                            tenant: tenantSlug,
                            application: review.application.id,
                        })}
                        idPrefix="decision"
                        submitLabel="Registrar decisión"
                    />
                </section>
            )}
            {review.previous !== null && (
                <ReviewDecisionCard
                    title="Validación anterior del lugar"
                    record={review.previous}
                />
            )}
            {review.workplace.status !== 'retired' && (
                <div className="flex flex-col items-start gap-2">
                    <ReviewAskDialog
                        tenantSlug={tenantSlug}
                        workplaceId={workplaceId}
                        kind="photos"
                    />
                    <ReviewAskDialog
                        tenantSlug={tenantSlug}
                        workplaceId={workplaceId}
                        kind="remote_check"
                    />
                    <ReviewRemoteCheckDialog
                        tenantSlug={tenantSlug}
                        workplaceId={workplaceId}
                    />
                </div>
            )}
        </aside>
    );
}
