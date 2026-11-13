import { retroText } from '@/lib/compensation/start';
import type { CompareScenario } from '@/types';

/** A scenario of the comparison as a card (board V4-Comp-Comparar): what it proposes, its first year and each year after. */
export default function CompareCard({
    scenario,
}: {
    scenario: CompareScenario;
}) {
    return (
        <article className="grid content-start gap-4 card p-5">
            <div className="grid gap-1.5">
                <h2 className="font-semibold">{scenario.name}</h2>
                <p className="text-sm text-muted-foreground">
                    {scenario.raise_text} ·{' '}
                    {retroText(scenario.retroactive_months)}
                </p>
            </div>
            <div className="grid gap-1 border-t pt-4">
                <p className="text-sm text-muted-foreground">Primer año</p>
                <p className="text-3xl leading-10 font-semibold tracking-[-0.015em] tabular-nums">
                    {scenario.first_year_text}
                </p>
                {scenario.above_lowest_text !== null && (
                    <p className="text-sm text-muted-foreground">
                        {scenario.above_lowest_text} sobre la más baja
                    </p>
                )}
                <p className="text-sm">
                    Al año, al arranque:{' '}
                    <strong className="font-semibold tabular-nums">
                        {scenario.recurring_text}
                    </strong>
                </p>
            </div>
        </article>
    );
}
