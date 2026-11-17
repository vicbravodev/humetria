import { CalendarRange, CircleCheck, ListChecks } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

type Point = { icon: LucideIcon; title: string; detail: string | null };

/** «Antes de empezar»: what the norm asks to say (C-17) as short lines in bold, each with its detail in gray. */
export default function BeforeStart({ period }: { period: string | null }) {
    const points: Point[] = [
        {
            icon: CircleCheck,
            title: 'No hay respuestas correctas ni incorrectas.',
            detail: null,
        },
        ...(period === null
            ? []
            : [
                  {
                      icon: CalendarRange,
                      title: `Piense en ${period}.`,
                      detail: 'Considere las condiciones de su trabajo en ese tiempo.',
                  },
              ]),
        {
            icon: ListChecks,
            title: 'Conteste todas las preguntas.',
            detail: 'Con calma y con sinceridad: su opinión es lo más importante.',
        },
    ];

    return (
        <section className="rounded-lg border bg-card px-4 pt-4 pb-1">
            <h2 className="mb-1 text-sm font-medium tracking-[0.02em] text-muted-foreground uppercase">
                Antes de empezar
            </h2>
            <ul>
                {points.map(({ icon: Icon, title, detail }) => (
                    <li
                        key={title}
                        className="flex items-start gap-3 border-t py-3 first:border-t-0"
                    >
                        <Icon
                            aria-hidden="true"
                            className="mt-0.5 size-5 shrink-0 text-primary"
                        />
                        <p>
                            <strong className="block font-semibold">
                                {title}
                            </strong>
                            {detail !== null && (
                                <span className="text-muted-foreground">
                                    {detail}
                                </span>
                            )}
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}
