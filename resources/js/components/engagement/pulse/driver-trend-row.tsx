import TrendChangeCell from '@/components/engagement/pulse/trend-change-cell';
import TrendMeasurement from '@/components/engagement/pulse/trend-measurement';
import TrendSparkline from '@/components/engagement/pulse/trend-sparkline';
import { TableCell, TableRow } from '@/components/ui/table';
import { changeSinceFirst } from '@/lib/engagement/trend';
import type { TrendSeries, TrendWave } from '@/types';

/** One theme: its name and anchor question, the line across the waves, its first and latest measurement and the change. */
export default function DriverTrendRow({
    series,
    waves,
}: {
    series: TrendSeries;
    waves: TrendWave[];
}) {
    const change = changeSinceFirst(series.points);
    const first = series.points[0];
    const latest = series.points.length > 1 ? series.points.at(-1) : undefined;

    return (
        <TableRow className="md:h-[72px]">
            <TableCell className="whitespace-normal max-md:w-full">
                <span className="block font-medium">{series.title}</span>
                {series.hint !== null && (
                    <span className="block text-sm text-muted-foreground">
                        {series.hint}
                    </span>
                )}
            </TableCell>
            <TableCell>
                <TrendSparkline
                    points={series.points}
                    waves={waves}
                    falling={change?.band === 'down'}
                />
            </TableCell>
            <TableCell className="md:text-right">
                <TrendMeasurement point={first} />
            </TableCell>
            <TableCell className="md:text-right">
                <TrendMeasurement point={latest} />
            </TableCell>
            <TableCell className="md:text-right">
                <TrendChangeCell
                    change={change}
                    measurements={series.points.length}
                />
            </TableCell>
        </TableRow>
    );
}
