import TrendBadge from '@/components/engagement/results/trend-badge';
import BarMeter from '@/components/humetria/bar-meter';
import { favorableText, shareWidth } from '@/lib/engagement/format';
import type { EngagementDriver } from '@/types';

/**
 * One driver of the company: its favorable share as a bar and how it moved. A driver of two questions has no score of its
 * own, so it lists its questions instead.
 */
export default function DriverRow({
    driver,
    index,
}: {
    driver: EngagementDriver;
    /** Its place in the list, so the bars grow one after another. */
    index: number;
}) {
    return (
        <li className="flex flex-col gap-2">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <span className="font-medium">{driver.label}</span>
                <span className="flex items-baseline gap-3">
                    <TrendBadge delta={driver.delta} />
                    {!driver.descriptive && (
                        <span className="font-semibold tabular-nums">
                            {favorableText(driver.favorable)}
                        </span>
                    )}
                </span>
            </div>
            {driver.descriptive ? (
                <>
                    <p className="text-caption text-muted-foreground">
                        Se reporta pregunta por pregunta.
                    </p>
                    <ul className="flex flex-col gap-2">
                        {driver.items.map((item, position) => (
                            <li key={item.key} className="grid gap-1">
                                <span className="flex flex-wrap justify-between gap-x-4 text-sm text-muted-foreground">
                                    {item.text}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {favorableText(item.favorable)}
                                    </span>
                                </span>
                                <BarMeter
                                    percent={shareWidth(item.favorable)}
                                    tooltip={`${item.text}: ${favorableText(item.favorable)}`}
                                    index={index + position}
                                />
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <BarMeter
                    percent={shareWidth(driver.favorable)}
                    tooltip={`${driver.label}: ${favorableText(driver.favorable)}`}
                    index={index}
                />
            )}
        </li>
    );
}
