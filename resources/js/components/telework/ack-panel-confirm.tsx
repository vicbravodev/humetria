import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { acknowledge } from '@/routes/tenant/telework/documents';

/** A Comisión member confirms in the panel that they read the document; it records the time and the address. */
export default function AckPanelConfirm({
    documentId,
}: {
    documentId: string;
}) {
    const tenantSlug = useTenantSlug();
    const form = useForm({});

    return (
        <Button
            disabled={form.processing}
            onClick={() =>
                form.post(
                    acknowledge.url({
                        tenant: tenantSlug,
                        document: documentId,
                    }),
                )
            }
        >
            {form.processing ? <Spinner /> : <Check aria-hidden />}
            Confirmo que lo leí
        </Button>
    );
}
