import { cn } from '@/lib/utils';

const RESULTS = [
    {
        letter: 'A',
        title: 'En su lugar de trabajo',
        text: 'Asiento o silla con respaldo, con las características de su espacio.',
    },
    {
        letter: 'B',
        title: 'Cerca de su lugar',
        text: 'Asiento en un lugar determinado cerca de donde trabaja.',
    },
    {
        letter: 'C',
        title: 'Dentro del centro de trabajo',
        text: 'Asiento en un lugar determinado dentro del centro.',
    },
] as const;

/** «Resultado» (board LS-Puesto): the three results of Figura 1, the one the answers give so far highlighted. */
export default function FigureOneResult({
    result,
}: {
    result: 'A' | 'B' | 'C' | null;
}) {
    return (
        <section
            aria-labelledby="figure-one-result"
            aria-live="polite"
            className="flex flex-col gap-3 self-start"
        >
            <h3 id="figure-one-result" className="text-sm font-semibold">
                Resultado
            </h3>
            {RESULTS.map((each) => {
                const on = each.letter === result;

                return (
                    <div
                        key={each.letter}
                        aria-current={on ? 'true' : undefined}
                        className={cn(
                            'flex items-start gap-3 card p-3.5',
                            on
                                ? 'border-2 border-primary bg-accent p-[13px]'
                                : 'text-muted-foreground',
                        )}
                    >
                        <span
                            className={cn(
                                'inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-xl font-semibold',
                                on
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted',
                            )}
                        >
                            {each.letter}
                        </span>
                        <span className="flex flex-col">
                            <span
                                className={cn(
                                    'font-medium',
                                    on && 'text-foreground',
                                )}
                            >
                                {each.title}
                            </span>
                            <span className="text-sm">{each.text}</span>
                        </span>
                    </div>
                );
            })}
        </section>
    );
}
