import TrendSince from '@/components/engagement/pulse/trend-since';
import { Badge } from '@/components/ui/badge';
import { changeLabel } from '@/lib/engagement/trend';
import type { TrendChange } from '@/lib/engagement/trend';

/**
 * How much a theme moved since its first measurement: what fell, as a terracotta pill with its words; what rose or held,
 * as an arrow and its points; and why there is no comparison when the waves are too small for one.
 */
export default function TrendChangeCell({
    change,
    measurements,
}: {
    change: TrendChange | null;
    measurements: number;
}) {
    if (change === null) {
        return (
            <span className="text-sm text-muted-foreground">
                {measurements < 2
                    ? 'Una sola medición'
                    : 'Menos de 30 respuestas: sin comparación'}
            </span>
        );
    }

    if (change.band === 'down') {
        return <Badge variant="destructive">{changeLabel(change)}</Badge>;
    }

    return <TrendSince change={change} bare />;
}
