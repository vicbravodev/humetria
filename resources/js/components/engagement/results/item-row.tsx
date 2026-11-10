import DistributionBar from '@/components/engagement/results/distribution-bar';
import TrendBadge from '@/components/engagement/results/trend-badge';
import { favorableText } from '@/lib/engagement/format';
import type { EngagementItemDetail } from '@/types';

/** One question with its favorable share, how the answers split and how many gave one. */
export default function ItemRow({ item }: { item: EngagementItemDetail }) {
    return (
        <li className="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-1">
                <p className="max-w-[560px] text-pretty">{item.text}</p>
                <span className="flex items-baseline gap-3">
                    <TrendBadge delta={item.delta} />
                    <strong className="font-semibold tabular-nums">
                        {favorableText(item.favorable)}
                    </strong>
                </span>
            </div>
            {item.distribution !== null && (
                <DistributionBar distribution={item.distribution} />
            )}
            <p className="text-caption text-muted-foreground">
                {item.answered === null
                    ? 'Menos de 5 personas la contestaron.'
                    : `${item.answered} respuestas`}
            </p>
        </li>
    );
}
