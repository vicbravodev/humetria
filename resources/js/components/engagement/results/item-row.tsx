import { Lock } from 'lucide-react';
import ShareBands from '@/components/engagement/results/share-bands';
import TrendBadge from '@/components/engagement/results/trend-badge';
import BarMeter from '@/components/humetria/bar-meter';
import type { EngagementItemDetail } from '@/types';

/**
 * One question (pantalla Clima · Por pregunta): its favorable share against the theme (the vertical mark), how many
 * answered it, how the answers split in three and how it moved. Under the minimum, a lock and why: never how many.
 */
export default function ItemRow({
    item,
    reference,
}: {
    item: EngagementItemDetail;
    reference: number | null;
}) {
    return (
        <li className="flex flex-col gap-2.5 py-5 first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-1">
                <p className="max-w-[560px] text-pretty">{item.text}</p>
                {item.favorable !== null && (
                    <span className="flex items-baseline gap-3 tabular-nums">
                        <TrendBadge delta={item.delta} />
                        <strong className="text-lg font-semibold">
                            {item.favorable} %
                        </strong>
                    </span>
                )}
            </div>
            {item.favorable === null || item.answered === null ? (
                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Lock aria-hidden className="size-4" />
                    Menos de 5 respuestas: no se muestra.
                </p>
            ) : (
                <>
                    <BarMeter
                        percent={item.favorable}
                        tooltip={`${item.text}: ${item.favorable} %`}
                        {...(reference === null ? {} : { reference })}
                    />
                    <ShareBands item={item} />
                    <p className="text-caption text-muted-foreground">
                        {item.answered} respuestas
                    </p>
                </>
            )}
        </li>
    );
}
