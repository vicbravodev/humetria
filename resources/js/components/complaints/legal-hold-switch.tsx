import { router } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { cn } from '@/lib/utils';
import { hold } from '@/routes/tenant/complaints/cases';

/**
 * «Conservar por un proceso legal» (board V4-Quejas-Caso, criterio C-48): while it is on, the content is not purged when the
 * retention ends. Turning it on or off is audited.
 */
export default function LegalHoldSwitch({
    caseId,
    onHold,
}: {
    caseId: string;
    onHold: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div className="flex items-center justify-between gap-4">
            <div className="flex flex-col">
                <span id="legal-hold" className="text-sm font-medium">
                    Conservar por un proceso legal
                </span>
                <span
                    id="legal-hold-hint"
                    className="text-sm text-muted-foreground"
                >
                    No se borra aunque venza el plazo.
                </span>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={onHold}
                aria-labelledby="legal-hold"
                aria-describedby="legal-hold-hint"
                onClick={() =>
                    router.post(
                        hold.url({ tenant: tenantSlug, complaint: caseId }),
                        { on_hold: onHold ? '0' : '1' },
                        { preserveScroll: true },
                    )
                }
                className={cn(
                    'touch-hit relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    onHold ? 'bg-primary' : 'bg-input',
                )}
            >
                <span
                    aria-hidden
                    className={cn(
                        'inline-block size-5 rounded-full bg-background shadow-raised transition-transform',
                        onHold ? 'translate-x-5' : 'translate-x-0',
                    )}
                />
            </button>
        </div>
    );
}
