import { Fingerprint } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { WeekAction } from '@/hooks/use-week';
import type { WeekView } from '@/lib/attendance/week';
import { weekActions } from '@/lib/attendance/week-text';

/**
 * The buttons at the foot of «Mi semana»: first the one that opens the week with the person's fingerprint, face or PIN; then
 * «Confirmar mi semana» and «No estoy de acuerdo», of the same size. Each asks the device again; nothing is answered for
 * the person.
 */
export default function WeekActions({
    week,
    working,
    onRead,
    onConfirm,
    onDispute,
}: {
    week: WeekView | null;
    working: WeekAction | null;
    onRead: () => void;
    onConfirm: (url: string) => void;
    onDispute: () => void;
}) {
    const actions = weekActions(week, false);
    const confirmUrl = week?.confirmUrl ?? null;

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
            {actions.includes('confirm') && confirmUrl !== null && (
                <Button
                    className="h-14 w-full text-[17px] font-semibold"
                    disabled={working !== null}
                    onClick={() => onConfirm(confirmUrl)}
                >
                    <Fingerprint aria-hidden className="size-5" />
                    Confirmar mi semana
                </Button>
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
