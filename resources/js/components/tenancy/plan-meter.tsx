import ProgressBar from '@/components/humetria/progress-bar';
import { useLine } from '@/lib/plan';
import type { PlanUse } from '@/types';

/** One limit of the plan: what is used, against what it covers, in a count, a bar and a sentence. */
export default function PlanMeter({
    title,
    use,
    noun,
}: {
    title: string;
    use: PlanUse;
    noun: { one: string; many: string };
}) {
    return (
        <div className="grid gap-3 card p-5">
            <h3 className="text-base font-semibold">{title}</h3>
            <p className="text-2xl font-semibold tracking-[-0.01em]">
                {use.used}{' '}
                {use.limit !== null && (
                    <span className="text-sm font-normal text-muted-foreground">
                        de {use.limit}
                    </span>
                )}
            </p>
            {use.limit !== null && (
                <ProgressBar
                    value={use.used}
                    max={use.limit}
                    label={`${title} en uso`}
                />
            )}
            <p className="text-sm text-muted-foreground">
                {useLine(use, noun)}
            </p>
        </div>
    );
}
