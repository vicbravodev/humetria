import SignedColumns from '@/components/engagement/pulse/signed-columns';
import ColumnChart from '@/components/humetria/column-chart';
import { baselineOf, columnsOf } from '@/lib/engagement/trend';
import type { TrendSeries } from '@/types';

/**
 * The waves of one series as columns from zero, oldest at the left, each with its month below: a share on 0 to 100 (with
 * the first measurement as its baseline when asked), the recommendation index around zero.
 */
export default function TrendColumns({
    series,
    withBaseline = false,
}: {
    series: TrendSeries;
    withBaseline?: boolean;
}) {
    if (series.kind === 'index') {
        return <SignedColumns label={series.title} points={series.points} />;
    }

    const baseline = withBaseline ? baselineOf(series.points) : undefined;

    return (
        <ColumnChart
            label={series.title}
            columns={columnsOf(series.points, series.kind)}
            {...(baseline === undefined ? {} : { baseline })}
        />
    );
}
