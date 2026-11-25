import { LogIn } from 'lucide-react';
import { useState } from 'react';
import SupportEntryDialog from '@/components/tenancy/support-entry-dialog';
import { Button } from '@/components/ui/button';

/** Entering a company as support, when it has an owner to work as: it asks why and for how long. It changes nothing about the company. */
export default function TenantSupportActions({
    tenantId,
    tradeName,
    hasOwner,
    variant = 'ghost',
}: {
    tenantId: string;
    tradeName: string;
    hasOwner: boolean;
    variant?: 'ghost' | 'outline';
}) {
    const [open, setOpen] = useState(false);

    if (!hasOwner) {
        return null;
    }

    return (
        <>
            <Button
                variant={variant}
                size={variant === 'ghost' ? 'sm' : 'default'}
                onClick={() => setOpen(true)}
                aria-label={`Entrar como soporte a ${tradeName}`}
            >
                <LogIn />
                Entrar como soporte
            </Button>
            <SupportEntryDialog
                tenantId={tenantId}
                tradeName={tradeName}
                open={open}
                onOpenChange={setOpen}
            />
        </>
    );
}
