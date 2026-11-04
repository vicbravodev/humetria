import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { exportMethod } from '@/routes/tenant/complaints/evidence';

/** Prints the evidence. It is shown to inspectors, so asking for it is recorded before the browser's dialog opens. */
export default function PrintEvidenceButton() {
    const tenantSlug = useTenantSlug();

    return (
        <Button
            onClick={() =>
                router.post(
                    exportMethod.url(tenantSlug),
                    {},
                    { preserveScroll: true, onSuccess: () => window.print() },
                )
            }
        >
            <Printer aria-hidden />
            Imprimir o guardar PDF
        </Button>
    );
}
