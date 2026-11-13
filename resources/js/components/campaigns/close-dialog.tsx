import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { closeShortfall, closeWarnings } from '@/lib/campaign-close';
import { close } from '@/routes/tenant/campaigns';
import type { CampaignProgress } from '@/types';

/**
 * Closes the evaluation now, opened from the page's menu. With people left it says so and asks to confirm: the norm asks
 * the guides of everyone in the center, or of the sample (III.2 c), and the record keeps how many answered (C-57).
 */
export default function CloseDialog({
    campaignId,
    progress,
    open,
    onOpenChange,
}: {
    campaignId: string;
    progress: CampaignProgress | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    const [acknowledged, setAcknowledged] = useState(false);
    const warnings = closeWarnings(progress);
    const { main } = closeShortfall(progress);
    const counted =
        progress === null || progress.againstSample || main === 0
            ? ''
            : ` Si cierras ahora, quedará registrado que contestaron ${progress.completed} de ${progress.target}.`;

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={(next) => {
                setAcknowledged(false);
                onOpenChange(next);
            }}
            title="¿Cerrar la evaluación?"
            description="Los enlaces dejan de funcionar y no se puede volver a abrir."
            confirmLabel="Cerrar evaluación"
            form={close.form([tenantSlug, campaignId])}
            transform={() =>
                acknowledged ? { acknowledged_shortfall: '1' } : {}
            }
            disabled={warnings.length > 0 && !acknowledged}
        >
            {warnings.length > 0 && (
                <div className="grid gap-3 rounded-lg border border-destructive/40 p-3 text-sm">
                    <div className="grid gap-1 text-destructive">
                        {warnings.map((line) => (
                            <p key={line}>{line}</p>
                        ))}
                    </div>
                    <p>
                        La norma pide las guías a todas las personas del centro.
                        {counted}
                    </p>
                    <div className="flex items-start gap-4">
                        <Checkbox
                            id="acknowledged-shortfall"
                            checked={acknowledged}
                            onCheckedChange={(next) =>
                                setAcknowledged(next === true)
                            }
                            className="mt-0.5"
                        />
                        <Label htmlFor="acknowledged-shortfall">
                            Entiendo y quiero cerrar así
                        </Label>
                    </div>
                </div>
            )}
        </ConfirmDialog>
    );
}
