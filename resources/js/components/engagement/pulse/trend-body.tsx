import DriverTrendList from '@/components/engagement/pulse/driver-trend-list';
import TrendCard from '@/components/engagement/pulse/trend-card';
import { trendReading } from '@/lib/engagement/trend';
import type { TrendSeries, TrendWave } from '@/types';

/**
 * The trend with something to compare (pantalla Pulso · Tendencia): the sentence that reads it, the three indices with
 * the engagement as protagonist, then the ten themes as one table.
 */
export default function TrendBody({
    series,
    waves,
}: {
    series: TrendSeries[];
    waves: TrendWave[];
}) {
    const [index, recommendation, loop, ...drivers] = series;
    const reading = trendReading(
        index?.points ?? [],
        recommendation?.points ?? [],
    );

    return (
        <>
            {reading !== null && (
                <h2 className="max-w-[720px] text-xl leading-7 font-semibold text-pretty">
                    {reading}
                </h2>
            )}
            <div className="grid gap-4 lg:grid-cols-[2fr_1fr_1fr]">
                {index !== undefined && <TrendCard series={index} prominent />}
                {recommendation !== undefined && (
                    <TrendCard series={recommendation} />
                )}
                {loop !== undefined && <TrendCard series={loop} />}
            </div>
            <DriverTrendList series={drivers} waves={waves} />
        </>
    );
}
