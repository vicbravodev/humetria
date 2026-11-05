import { Form } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { placeReview } from '@/routes/tenant/telework/notices';
import type { AccidentNotice } from '@/types';

/** «¿Hay que revisar su lugar?» (board V3-Aviso): asks the Comisión to review the person's place again. */
export default function AccidentPlaceReview({
    notice,
}: {
    notice: AccidentNotice;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="accident-place"
            className="flex flex-col gap-3 card p-5"
        >
            <h2 id="accident-place" className="font-semibold">
                ¿Hay que revisar su lugar?
            </h2>
            <p className="text-sm text-muted-foreground">
                Si el accidente tuvo que ver con su espacio, pide a la Comisión
                que lo revise de nuevo.
            </p>
            {notice.placeReviewOn !== null && (
                <p className="text-sm font-medium">
                    Lo pediste el{' '}
                    {describeDate(notice.placeReviewOn) ?? notice.placeReviewOn}
                    : la persona vuelve a contestar la lista de su lugar y la
                    Comisión la valida.
                </p>
            )}
            {notice.placeReviewOn === null && notice.canAskPlaceReview && (
                <Form
                    {...placeReview.form({
                        tenant: tenantSlug,
                        notice: notice.id,
                    })}
                    options={{ preserveScroll: true }}
                    className="grid gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                {processing && <Spinner />}
                                Pedir revisión del lugar
                            </Button>
                            <InputError
                                message={
                                    errors['notice'] ?? errors['work_center']
                                }
                            />
                        </>
                    )}
                </Form>
            )}
            {notice.placeReviewOn === null && !notice.canAskPlaceReview && (
                <p className="text-sm">
                    Se puede pedir cuando la persona tenga un lugar en uso y la
                    Comisión haya publicado su lista.
                </p>
            )}
        </section>
    );
}
