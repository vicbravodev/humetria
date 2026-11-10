import TrendBadge from '@/components/engagement/results/trend-badge';
import { favorableText } from '@/lib/engagement/format';
import type { EngagementLoopAction } from '@/types';

/**
 * Whether the company closed the loop: how many have seen the improvements it presented after the previous survey. The
 * figure shows from five answers, as every other.
 */
export default function LoopActionCard({
    loopAction,
}: {
    loopAction: EngagementLoopAction;
}) {
    return (
        <section
            aria-label="Mejoras vistas"
            className="flex flex-col gap-3 card p-5 sm:p-6"
        >
            <h2 className="text-lg font-semibold">Mejoras vistas</h2>
            {loopAction.shown ? (
                <>
                    <p className="text-[34px] leading-10 font-semibold tracking-[-0.02em] tabular-nums">
                        {favorableText(loopAction.favorable)}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        ha visto mejoras que la empresa presentó tras la
                        encuesta anterior.
                    </p>
                    <TrendBadge delta={loopAction.vsPrevious} />
                </>
            ) : (
                <p className="text-muted-foreground">{favorableText(null)}.</p>
            )}
        </section>
    );
}
