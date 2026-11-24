import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * «Resumen» beside the areas (board Roles 7c): what the role or the person can do in one plain sentence, a few facts, then
 * the confirmation of sensitive permissions and the buttons. A column that stays in view on a wide screen; under the areas on
 * a phone.
 */
export default function AccessSummary({
    sentence,
    facts,
    children,
    className,
}: {
    sentence: string;
    facts: { label: string; value: string }[];
    children?: ReactNode;
    className?: string;
}) {
    return (
        <aside
            aria-labelledby="access-summary"
            className={cn(
                'flex flex-col gap-4 card p-5 lg:sticky lg:top-20 lg:p-6',
                className,
            )}
        >
            <h2 id="access-summary" className="text-lg font-semibold">
                Resumen
            </h2>
            <p className="text-pretty" data-test="access-sentence">
                {sentence}
            </p>
            <dl className="flex flex-col">
                {facts.map((fact) => (
                    <div
                        key={fact.label}
                        className="flex justify-between gap-3 border-t py-2.5 text-sm"
                    >
                        <dt className="text-muted-foreground">{fact.label}</dt>
                        <dd className="font-semibold tabular-nums">
                            {fact.value}
                        </dd>
                    </div>
                ))}
            </dl>
            {children}
        </aside>
    );
}
