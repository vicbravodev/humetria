import { Fingerprint } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { confirmBlockText } from '@/lib/attendance/week-text';
import type { ConfirmBlock } from '@/lib/attendance/week-text';

/**
 * «Confirmar mi semana». While days without cover block it the button is turned off and the line under it, which the button
 * points to, says which days are missing; the server sends no address to confirm then.
 */
export default function WeekConfirmButton({
    block,
    busy,
    onConfirm,
}: {
    block: ConfirmBlock | null;
    busy: boolean;
    onConfirm: () => void;
}) {
    return (
        <>
            <Button
                className="h-14 w-full text-[17px] font-semibold"
                disabled={busy || block !== null}
                aria-describedby={
                    block === null ? undefined : 'week-confirm-block'
                }
                onClick={onConfirm}
            >
                <Fingerprint aria-hidden className="size-5" />
                Confirmar mi semana
            </Button>
            {block === null && (
                <p className="text-center text-base text-pretty text-muted-foreground">
                    Al confirmar, RH ve que usted revisó sus marcas.
                </p>
            )}
            {block !== null && (
                <p
                    id="week-confirm-block"
                    className="text-center text-base text-pretty text-muted-foreground"
                >
                    {confirmBlockText(block)}
                </p>
            )}
        </>
    );
}
