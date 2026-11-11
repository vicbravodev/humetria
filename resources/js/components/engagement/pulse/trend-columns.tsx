import ColumnChart from '@/components/humetria/column-chart';
import { columnsOf } from '@/lib/engagement/trend';
import type { TrendSeries } from '@/types';

/** The waves of one series as columns, oldest at the left, each with its month below. */
export default function TrendColumns({ series }: { series: TrendSeries }) {
    return (
        <ColumnChart
            label={series.title}
            columns={columnsOf(series.points, series.kind)}
        />
    );
}
