import { router, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { firstName, isBlocked } from '@/lib/attendance/correction-effects';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { approve, index, reject } from '@/routes/tenant/attendance/corrections';
import type { CorrectionDetail, CorrectionStatus } from '@/types';

/** The note and the two answers; the approval is held back while the server would refuse it. Resolved once, for good. */
export default function CorrectionDecision({
    detail,
    status,
    q,
}: {
    detail: CorrectionDetail;
    status: CorrectionStatus;
    q: string;
}) {
    const tenantSlug = useTenantSlug();
    const form = useForm({ note: '' });
    const route = { tenant: tenantSlug, correction: detail.id };
    // The server sends back to the page as it was; asking for this request again keeps how it ended in view.
    const send = (action: typeof approve | typeof reject) =>
        form.post(action.url(route), {
            preserveScroll: true,
            onSuccess: () =>
                router.get(
                    index.url(tenantSlug, {
                        query: {
                            ...correctionsQuery({ q, status }),
                            request: detail.id,
                        },
                    }),
                    {},
                    { preserveScroll: true, preserveState: true },
                ),
        });

    return (
        <div className="flex flex-col gap-3.5 border-t pt-6">
            <div className="grid gap-2">
                <Label htmlFor="correction-note">
                    Nota para {firstName(detail.person.name)}{' '}
                    <span className="font-normal text-muted-foreground">
                        (obligatoria si la rechazas)
                    </span>
                </Label>
                <Textarea
                    id="correction-note"
                    name="note"
                    rows={3}
                    value={form.data.note}
                    onChange={(event) =>
                        form.setData('note', event.target.value)
                    }
                    aria-invalid={
                        form.errors.note === undefined ? undefined : true
                    }
                    placeholder="Por ejemplo: Aprobada. La próxima vez avisa a tu supervisor el mismo día."
                />
                <InputError message={form.errors.note} />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    Se resuelve una sola vez. No se puede deshacer.
                </p>
                <div className="flex gap-2.5">
                    <Button
                        id="reject-correction"
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => send(reject)}
                    >
                        <X aria-hidden />
                        Rechazar
                    </Button>
                    <Button
                        id="approve-correction"
                        type="button"
                        disabled={form.processing || isBlocked(detail)}
                        onClick={() => send(approve)}
                    >
                        {form.processing ? <Spinner /> : <Check aria-hidden />}
                        Aprobar corrección
                    </Button>
                </div>
            </div>
        </div>
    );
}
