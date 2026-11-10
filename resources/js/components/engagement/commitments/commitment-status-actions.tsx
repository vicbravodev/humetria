import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { nextStatuses } from '@/lib/engagement/commitments';
import { status as statusRoute } from '@/routes/tenant/climate/commitments';
import type { CommitmentRow } from '@/types';

const VERB: Record<CommitmentRow['status'], string> = {
    open: 'Reabrir',
    in_progress: 'Marcar en marcha',
    done: 'Marcar cumplido',
    dropped: 'Descartar',
};

/** Moves a commitment to another state: one small button per state it can go to. */
export default function CommitmentStatusActions({
    surveyId,
    commitment,
}: {
    surveyId: string;
    commitment: CommitmentRow;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            {nextStatuses(commitment.status).map((next) => (
                <Form
                    key={next}
                    {...statusRoute.form.put([
                        tenantSlug,
                        surveyId,
                        commitment.id,
                    ])}
                    options={{ preserveScroll: true }}
                    className="inline"
                >
                    {({ processing }) => (
                        <>
                            <input type="hidden" name="status" value={next} />
                            <Button
                                variant="ghost"
                                size="sm"
                                disabled={processing}
                            >
                                {VERB[next]}
                            </Button>
                        </>
                    )}
                </Form>
            ))}
        </>
    );
}
