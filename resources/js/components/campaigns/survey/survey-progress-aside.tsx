import { CircleCheck } from 'lucide-react';
import type { StepProgress } from '@/lib/survey';
import { cn } from '@/lib/utils';
import type { SurveyInstrument } from '@/types';

/** "Su avance" on a computer: each guide sent, the one in course with its section, and the ones still to come. */
export default function SurveyProgressAside({
    instruments,
    current,
    progress,
}: {
    instruments: SurveyInstrument[];
    current: string;
    progress: StepProgress | undefined;
}) {
    return (
        <aside className="hidden w-[220px] shrink-0 flex-col pt-2 md:flex">
            <h2 className="mb-1 text-base font-semibold">Su avance</h2>
            {instruments.map((instrument) => {
                const sent = instrument.status === 'completed';
                const active = instrument.key === current;

                return (
                    <div key={instrument.key} className="flex flex-col">
                        <span className="pt-3 pb-1 text-base font-medium text-muted-foreground">
                            {instrument.shortTitle}
                        </span>
                        <div className="flex min-h-9 items-center gap-2.5 text-base">
                            {sent ? (
                                <CircleCheck
                                    aria-hidden="true"
                                    className="size-[18px] text-primary"
                                />
                            ) : (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'size-[18px] rounded-full',
                                        active
                                            ? 'border-[5px] border-primary'
                                            : 'border-[1.5px] border-input',
                                    )}
                                />
                            )}
                            <span
                                className={cn(
                                    active
                                        ? 'font-semibold'
                                        : !sent && 'text-muted-foreground',
                                )}
                            >
                                {sent
                                    ? 'Terminada'
                                    : active && progress !== undefined
                                      ? `Sección ${progress.section} de ${progress.sections}`
                                      : 'Pendiente'}
                            </span>
                        </div>
                        {active && progress !== undefined && (
                            <span className="pl-7 text-base text-muted-foreground">
                                {progress.answered} de {progress.inSection}{' '}
                                respondidas
                            </span>
                        )}
                    </div>
                );
            })}
        </aside>
    );
}
