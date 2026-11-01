import { router } from '@inertiajs/react';
import ConfirmAction from '@/components/telework/confirm-action';
import { Button } from '@/components/ui/button';
import { retire } from '@/routes/tenant/telework/workplaces';

/** Takes a place out of use (the person moved, or it is not apt). It stays in the history: nothing is deleted. */
export default function RetireWorkplaceButton({
    tenantSlug,
    workplaceId,
    address,
}: {
    tenantSlug: string;
    workplaceId: string;
    address: string;
}) {
    return (
        <ConfirmAction
            title="¿Retirar este lugar de trabajo?"
            description={`${address} dejará de usarse: ya no se le pedirán listas ni cuenta en la tabla de inspección. Queda en el historial.`}
            confirmLabel="Retirar lugar"
            destructive
            onConfirm={() =>
                router.post(
                    retire({ tenant: tenantSlug, workplace: workplaceId }),
                )
            }
        >
            <Button variant="outline" size="sm">
                Retirar lugar
            </Button>
        </ConfirmAction>
    );
}
