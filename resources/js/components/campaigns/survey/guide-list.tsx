import { Check } from 'lucide-react';
import type { SurveyInstrument } from '@/types';

/** The person's guides, numbered, each with what it is about, how long it takes and whether it was sent. */
export default function GuideList({
    instruments,
}: {
    instruments: SurveyInstrument[];
}) {
    return (
        <ol className="overflow-hidden rounded-lg border bg-card">
            {instruments.map((instrument, index) => (
                <li
                    key={instrument.key}
                    className="flex items-center gap-3 border-t px-4 py-3 first:border-t-0"
                >
                    <span
                        aria-hidden="true"
                        className="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-muted font-medium text-muted-foreground tabular-nums"
                    >
                        {index + 1}
                    </span>
                    <span className="flex min-w-0 flex-1 flex-col">
                        <span className="font-medium">
                            {instrument.shortTitle}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {instrument.title} · {instrument.minutes} min
                        </span>
                    </span>
                    {instrument.status === 'completed' ? (
                        <span className="flex shrink-0 items-center gap-1.5 text-sm font-medium text-primary">
                            <Check aria-hidden="true" className="size-4" />
                            Enviada
                        </span>
                    ) : (
                        <span className="shrink-0 text-sm font-medium text-muted-foreground">
                            Pendiente
                        </span>
                    )}
                </li>
            ))}
        </ol>
    );
}
