import { Circle, CircleCheck, LoaderCircle } from 'lucide-react';
import PersonFrame from '@/components/attendance/person-frame';
import { Spinner } from '@/components/ui/spinner';
import { phaseRows } from '@/lib/attendance/punch-phase';
import type { PunchPhase } from '@/lib/attendance/punch-phase';
import { cn } from '@/lib/utils';

/** While the mark is checked (board P2, «Verificando»): each test, in order, seen as it passes. */
export default function PunchProgress({
    company,
    phase,
}: {
    company: string;
    phase: PunchPhase;
}) {
    return (
        <PersonFrame company={company}>
            <div
                role="status"
                className="flex w-full max-w-md flex-col justify-between gap-8"
            >
                <div className="flex flex-col items-start justify-center gap-4 pt-8">
                    <Spinner className="size-16 text-primary" />
                    <h1 className="text-2xl leading-8 font-semibold">
                        Verificando
                    </h1>
                    <p className="text-muted-foreground">
                        Comprobamos el código y su ubicación. Con una conexión
                        lenta puede tardar unos segundos.
                    </p>
                </div>
                <ol className="flex flex-col gap-3">
                    {phaseRows(phase).map(({ label, state }) => (
                        <li
                            key={label}
                            aria-current={
                                state === 'current' ? 'step' : undefined
                            }
                            className={cn(
                                'flex items-center gap-3',
                                state === 'done'
                                    ? 'text-risk-bajo-fg'
                                    : state === 'pending'
                                      ? 'text-muted-foreground'
                                      : 'font-medium',
                            )}
                        >
                            {state === 'done' && (
                                <CircleCheck aria-hidden className="size-5" />
                            )}
                            {state === 'current' && (
                                <LoaderCircle
                                    aria-hidden
                                    className="size-5 animate-spin text-primary motion-reduce:animate-none"
                                />
                            )}
                            {state === 'pending' && (
                                <Circle aria-hidden className="size-5" />
                            )}
                            {label}
                        </li>
                    ))}
                </ol>
            </div>
        </PersonFrame>
    );
}
