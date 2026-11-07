import { Check, X } from 'lucide-react';
import { useState } from 'react';
import CorrectionDialog from '@/components/attendance/correction-dialog';
import { Button } from '@/components/ui/button';
import type { CorrectionRow } from '@/types';

/** Approve or reject one request; each opens its dialog. */
export default function CorrectionActions({
    correction,
}: {
    correction: CorrectionRow;
}) {
    const [open, setOpen] = useState<'approve' | 'reject' | null>(null);

    return (
        <div className="flex flex-wrap gap-2">
            <Button
                size="sm"
                onClick={() => setOpen('approve')}
                aria-label={`Aprobar la solicitud de ${correction.person.name}`}
            >
                <Check aria-hidden />
                Aprobar
            </Button>
            <Button
                size="sm"
                variant="outline"
                onClick={() => setOpen('reject')}
                aria-label={`Rechazar la solicitud de ${correction.person.name}`}
            >
                <X aria-hidden />
                Rechazar
            </Button>
            {open !== null && (
                <CorrectionDialog
                    open
                    onOpenChange={(next) => setOpen(next ? open : null)}
                    correction={correction}
                    approving={open === 'approve'}
                />
            )}
        </div>
    );
}
