import { Fingerprint } from 'lucide-react';
import WeekConfirmButton from '@/components/attendance/week-confirm-button';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { WeekAction } from '@/hooks/use-week';
import type { WeekView } from '@/lib/attendance/week';
import { confirmBlock, weekActions } from '@/lib/attendance/week-text';

/**
 * The buttons at the foot of «Mi semana»: first the one that opens the week with the person's fingerprint, face or PIN; then
 * «Confirmar mi semana» and «No estoy de acuerdo», of the same size. Each asks the device again; nothing is answered for
 * the person. With days without cover, or marks of the person that still wait in the phone, «Confirmar mi semana» is turned
 * off and the line under it says why; «No estoy de acuerdo» stays.
 */
export default function WeekActions({
    week,
    working,
    queued,
    onRead,
    onConfirm,
    onDispute,
}: {
    week: WeekView | null;
    working: WeekAction | null;
    /** How many of the person's marks wait in the phone: while any do, the week cannot be confirmed. */
    queued: number;
    onRead: () => void;
    onConfirm: (url: string) => void;
    onDispute: () => void;
}) {
    const actions = weekActions(week, false);
    const confirmUrl = week?.confirmUrl ?? null;
    const block = week === null ? null : confirmBlock(week, queued);

    return (
        <>
            {actions.includes('read') && (
                <Button
                    className="h-14 w-full text-[17px] font-semibold"
                    disabled={working !== null}
                    onClick={onRead}
                >
                    {working === 'read' ? (
                        <Spinner className="size-5" />
                    ) : (
                        <Fingerprint aria-hidden className="size-5" />
                    )}
                    Ver mi semana
                </Button>
            )}
            {actions.includes('confirm') && (
                <WeekConfirmButton
                    block={block}
                    busy={working !== null}
                    onConfirm={() =>
                        confirmUrl !== null && onConfirm(confirmUrl)
                    }
                />
            )}
            {actions.includes('dispute') && (
                <Button
                    variant="outline"
                    className="h-14 w-full text-[17px]"
                    disabled={working !== null}
                    onClick={onDispute}
                >
                    No estoy de acuerdo
                </Button>
            )}
        </>
    );
}
