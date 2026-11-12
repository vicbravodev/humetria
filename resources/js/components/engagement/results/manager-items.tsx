import { pointsText } from '@/lib/engagement/format';
import { dumbbellRows } from '@/lib/engagement/manager';
import type { FeedbackItem } from '@/types';

/**
 * Every question about the manager that reached the minimum as a dumbbell (pantalla Clima · Jefe): the company as a
 * hollow dot and the group as a solid one on a rule of 0 to 100 %, the difference at the right, ordered by it.
 */
export default function ManagerItems({ items }: { items: FeedbackItem[] }) {
    return (
        <section
            aria-labelledby="manager-items"
            className="flex flex-col gap-4 card p-5 sm:p-6"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="manager-items" className="text-xl font-semibold">
                    Pregunta por pregunta
                </h2>
                <ul className="flex gap-4 text-sm text-muted-foreground">
                    <li className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className="size-3 rounded-full border-2 border-muted-foreground bg-card"
                        />
                        Empresa
                    </li>
                    <li className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className="size-3 rounded-full bg-primary"
                        />
                        Su grupo
                    </li>
                </ul>
            </div>
            <ol className="flex flex-col divide-y">
                {dumbbellRows(items).map((row) => {
                    const low = Math.min(row.group, row.company ?? row.group);
                    const high = Math.max(row.group, row.company ?? row.group);

                    return (
                        <li
                            key={row.key}
                            className="grid gap-2 py-4 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_80px] md:items-center md:gap-6"
                        >
                            <p className="text-sm text-pretty">{row.text}</p>
                            <div
                                role="img"
                                aria-label={`Su grupo ${row.group} %${row.company === null ? '' : `, la empresa ${row.company} %`}`}
                                className="relative h-4"
                            >
                                <span className="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 bg-track" />
                                <span
                                    className="absolute top-1/2 h-1 -translate-y-1/2 bg-primary/40"
                                    style={{
                                        left: `${low}%`,
                                        width: `${high - low}%`,
                                    }}
                                />
                                {row.company !== null && (
                                    <span
                                        className="absolute top-1/2 size-3.5 -translate-1/2 rounded-full border-2 border-muted-foreground bg-card"
                                        style={{ left: `${row.company}%` }}
                                    />
                                )}
                                <span
                                    className="absolute top-1/2 size-3.5 -translate-1/2 rounded-full bg-primary"
                                    style={{ left: `${row.group}%` }}
                                />
                            </div>
                            <p className="text-sm tabular-nums md:text-right">
                                <strong className="font-semibold">
                                    {row.group} %
                                </strong>
                                {row.delta !== null && (
                                    <span className="ml-1.5 text-muted-foreground">
                                        {pointsText(row.delta)}
                                    </span>
                                )}
                            </p>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
