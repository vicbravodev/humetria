import { router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import OfflineIncorrectDialog from '@/components/attendance/offline-incorrect-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { verify } from '@/routes/tenant/attendance/offline';
import type { OfflineMarkDetail } from '@/types';

/**
 * The decision on a mark made without a connection: verify it (it changes nothing: its time is the one the server counted)
 * or say it is incorrect, which opens a dialog with its note and the time that is right. Whoever only views reads who
 * decides.
 */
export default function OfflineMarkDecision({
    detail,
    canResolve,
}: {
    detail: OfflineMarkDetail;
    canResolve: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    if (!canResolve) {
        return (
            <p className="border-t pt-5 text-sm text-muted-foreground">
                Solo el dueño y RH verifican las marcas.
            </p>
        );
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-6">
            <p className="text-sm text-muted-foreground">
                Verificar no cambia la marca: cuenta a la hora que ya tiene.
            </p>
            <div className="flex gap-2.5">
                <Button
                    id="mark-incorrect"
                    type="button"
                    variant="outline"
                    onClick={() => setOpen(true)}
                >
                    <X aria-hidden />
                    Es incorrecta
                </Button>
                <Button
                    id="verify-mark"
                    type="button"
                    disabled={processing}
                    onClick={() =>
                        router.post(
                            verify.url({
                                tenant: tenantSlug,
                                mark: detail.id,
                            }),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setProcessing(true),
                                onFinish: () => setProcessing(false),
                            },
                        )
                    }
                >
                    <Check aria-hidden />
                    Verificar
                </Button>
            </div>
            <OfflineIncorrectDialog
                detail={detail}
                open={open}
                onOpenChange={setOpen}
            />
        </div>
    );
}
