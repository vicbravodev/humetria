import { Fingerprint } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { WeekAction } from '@/hooks/use-week';
import type { WeekView } from '@/lib/attendance/week';
import { weekActions } from '@/lib/attendance/week-text';

/**
 * The buttons at the foot of «Mi semana» (board P4): first the one that opens the week with the person's fingerprint, face or
 * PIN; then to confirm it, or to say what is not right. Each asks the device again; nothing is answered for the person.
 */
export default function WeekActions({
    week,
    disputing,
    working,
    onRead,
    onConfirm,
    onDispute,
}: {
    week: WeekView | null;
    disputing: boolean;
    working: WeekAction | null;
    onRead: () => void;
    onConfirm: (url: string) => void;
    onDispute: () => void;
}) {
    const actions = weekActions(week, disputing);
    const confirmUrl = week?.confirmUrl ?? null;

    return (
        <>
            {actions.includes('read') && (
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    disabled={working !== null}
                    onClick={onRead}
                >
                    {working === 'read' ? (
                        <Spinner className="size-6" />
                    ) : (
                        <Fingerprint aria-hidden className="size-6" />
                    )}
                    Ver mi semana
                </Button>
            )}
            {actions.includes('confirm') && confirmUrl !== null && (
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    disabled={working !== null}
                    onClick={() => onConfirm(confirmUrl)}
                >
                    <Fingerprint aria-hidden className="size-6" />
                    Confirmar mi semana
                </Button>
            )}
            {actions.includes('dispute') && (
                <Button
                    variant="outline"
                    size="lg"
                    className="w-full text-base"
                    disabled={working !== null}
                    onClick={onDispute}
                >
                    No estoy de acuerdo
                </Button>
            )}
        </>
    );
}
