import { Fingerprint } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { MarkType } from '@/lib/attendance/next-step';
import { cn } from '@/lib/utils';

const BIG = 'h-14 text-[17px] font-semibold';
const SMALL = 'h-12 text-base';

/**
 * Nobody known in this browser (board «Asistencia: marcar, acciones en 2×2»): the four steps in two rows, «Entrada» first
 * and «Salida» next, the meal ones smaller. The server says which proceeds once the person signs.
 */
export default function PunchEveryStep({
    onMark,
}: {
    onMark: (type: MarkType) => void;
}) {
    return (
        <div className="grid grid-cols-2 gap-2.5">
            <Button className={BIG} onClick={() => onMark('start')}>
                <Fingerprint aria-hidden className="size-5" />
                Entrada
            </Button>
            <Button
                variant="outline"
                className={cn(BIG, 'border-2 border-primary bg-accent')}
                onClick={() => onMark('end')}
            >
                Salida
            </Button>
            <Button
                variant="outline"
                className={SMALL}
                onClick={() => onMark('break_start')}
            >
                Salir a comer
            </Button>
            <Button
                variant="outline"
                className={SMALL}
                onClick={() => onMark('break_end')}
            >
                Regresar de comer
            </Button>
        </div>
    );
}
