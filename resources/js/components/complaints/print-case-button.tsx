import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { exportMethod } from '@/routes/tenant/complaints/cases';

/** Prints the case. The printout leaves the platform, so asking for it is recorded before the browser's dialog opens. */
export default function PrintCaseButton({ caseId }: { caseId: string }) {
    const tenantSlug = useTenantSlug();

    return (
        <Button
            variant="outline"
            onClick={() =>
                router.post(
                    exportMethod.url({ tenant: tenantSlug, complaint: caseId }),
                    {},
                    { preserveScroll: true, onSuccess: () => window.print() },
                )
            }
        >
            <Printer aria-hidden />
            Imprimir
        </Button>
    );
}
