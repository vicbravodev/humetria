import { Check } from 'lucide-react';
import PersonFrame from '@/components/attendance/person-frame';
import { phaseRows } from '@/lib/attendance/punch-phase';
import type { PhaseRow, PunchPhase } from '@/lib/attendance/punch-phase';
import { cn } from '@/lib/utils';

// The shape of the "h" in the logo: up one leg, over the arch, down the other (ArcMeter).
const ARC = 'M10 49V33A22 22 0 0 1 54 33V49';

const STATE: Record<PhaseRow['state'], string> = {
    done: 'hecho',
    current: 'en curso',
    pending: 'pendiente',
};

/**
 * While the mark is checked (board «Asistencia: verificando la marca»): the arch of the logo with a stretch running along
 * it, the title and the line centered, and the three tests in a card, each with its state written as well as drawn. With
 * reduced motion everything is still.
 */
export default function PunchProgress({
    company,
    phase,
}: {
    company: string;
    phase: PunchPhase;
}) {
    return (
        <PersonFrame company={company} centered>
            <div
                role="status"
                className="flex w-full max-w-md flex-col items-center gap-4 text-center"
            >
                <svg
                    viewBox="0 5 64 46"
                    aria-hidden
                    className="block h-[113px] w-[156px] overflow-visible"
                >
                    <path
                        d={ARC}
                        fill="none"
                        className="stroke-track"
                        strokeWidth={5.22}
                        strokeLinecap="round"
                        pathLength={100}
                    />
                    <path
                        d={ARC}
                        fill="none"
                        className="arc-chase stroke-primary"
                        strokeWidth={5.22}
                        strokeLinecap="round"
                        pathLength={100}
                    />
                </svg>
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Verificando
                </h1>
                <p className="max-w-xs text-[17px] leading-[26px] text-muted-foreground">
                    Comprobamos el código y su ubicación. Con una conexión lenta
                    puede tardar unos segundos.
                </p>
                <ol className="mt-2 flex w-full flex-col gap-3.5 card p-4 text-left">
                    {phaseRows(phase).map(({ label, state }) => (
                        <li
                            key={label}
                            aria-current={
                                state === 'current' ? 'step' : undefined
                            }
                            className={cn(
                                'flex items-center gap-3 text-[17px] leading-6',
                                state === 'current' && 'font-medium',
                                state === 'pending' && 'text-muted-foreground',
                            )}
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full',
                                    state === 'done' &&
                                        'bg-primary text-primary-foreground',
                                    state === 'current' &&
                                        'animate-spin border-2 border-input border-t-primary motion-reduce:animate-none',
                                    state === 'pending' &&
                                        'border-2 border-input opacity-60',
                                )}
                            >
                                {state === 'done' && (
                                    <Check className="size-4" />
                                )}
                            </span>
                            {label}
                            <span className="sr-only">: {STATE[state]}</span>
                        </li>
                    ))}
                </ol>
            </div>
        </PersonFrame>
    );
}
