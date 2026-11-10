import { probeText } from '@/lib/attendance/zone-map';
import type { Probe } from '@/lib/attendance/zone-map';
import { cn } from '@/lib/utils';

/** What the plan says of the point RH touched: whether a mark made there would count, and how far it is from the center. */
export default function ZoneProbeNote({ probe }: { probe: Probe | null }) {
    const answer = probe === null ? null : probeText(probe);

    return (
        <p
            aria-live="polite"
            className={cn(
                'rounded-lg px-4 py-3 text-sm',
                answer === null && 'bg-muted text-muted-foreground',
                answer?.tone === 'ok' && 'bg-risk-bajo-bg text-risk-bajo-fg',
                answer?.tone === 'out' && 'bg-warning text-warning-foreground',
            )}
        >
            {answer === null ? (
                'Toca cualquier punto del plano para probar si una marca contaría.'
            ) : (
                <>
                    <strong className="font-semibold">{answer.lead}</strong>
                    {' · '}
                    {answer.text}
                </>
            )}
        </p>
    );
}
