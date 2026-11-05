import { accidentTimeline } from '@/lib/telework/accident';
import { cn } from '@/lib/utils';
import type { AccidentNotice } from '@/types';

/** «Línea de tiempo» of an accident (board V3-Aviso): what happened and was told, then what RH did or still has to. */
export default function AccidentTimeline({
    notice,
}: {
    notice: AccidentNotice;
}) {
    return (
        <section
            aria-labelledby="accident-timeline"
            className="flex flex-col gap-4 card p-5"
        >
            <h2 id="accident-timeline" className="font-semibold">
                Línea de tiempo
            </h2>
            <ol className="flex flex-col">
                {accidentTimeline(notice, notice.person).map((entry) => (
                    <li
                        key={entry.key}
                        className="relative grid grid-cols-[14px_minmax(0,1fr)] gap-3 pb-4 before:absolute before:top-4 before:bottom-0 before:left-[6px] before:w-px before:bg-border last:pb-0 last:before:hidden"
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'mt-1 size-3.5 rounded-full',
                                entry.tone === 'person' && 'bg-risk-alto',
                                entry.tone === 'done' && 'bg-primary',
                                entry.tone === 'pending' &&
                                    'border-[1.5px] border-input bg-card',
                            )}
                        />
                        <div>
                            <p className="text-sm font-medium">{entry.title}</p>
                            {entry.detail !== undefined && (
                                <p className="text-sm text-muted-foreground">
                                    {entry.detail}
                                </p>
                            )}
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}
