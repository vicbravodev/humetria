import type { ReactNode } from 'react';
import type { Result } from '@/lib/applicability';

/** What the answers mean for the standard (board V2-Aplica-Otras), next to the questions, with its one action. */
export default function AppliesResult({
    result,
    action,
}: {
    result: Result;
    action: ReactNode;
}) {
    return (
        <aside
            aria-live="polite"
            aria-labelledby="applies-result"
            className="flex flex-col gap-3.5 card p-6 md:p-7"
        >
            <p className="text-sm font-semibold text-primary">{result.tag}</p>
            <h2
                id="applies-result"
                className="text-2xl leading-8 font-semibold tracking-[-0.01em]"
            >
                {result.title}
            </h2>
            <p className="text-muted-foreground">{result.body}</p>
            {result.extra !== '' && (
                <p className="rounded-[10px] bg-muted px-3.5 py-3 text-sm">
                    {result.extra}
                </p>
            )}
            <div className="flex flex-wrap items-center gap-3">{action}</div>
        </aside>
    );
}
