import DriverTrendRow from '@/components/engagement/pulse/driver-trend-row';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { sortByChange } from '@/lib/engagement/trend';
import type { TrendSeries, TrendWave } from '@/types';

/**
 * The ten themes as one table ordered by how much they moved since their first measurement (pantalla Pulso ·
 * Tendencia): what rose most first, what cannot be compared last. On a phone each row is a card.
 */
export default function DriverTrendList({
    series,
    waves,
}: {
    series: TrendSeries[];
    waves: TrendWave[];
}) {
    return (
        <section
            aria-labelledby="trend-drivers"
            className="flex flex-col gap-3"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <h2 id="trend-drivers" className="text-xl font-semibold">
                    Por tema
                </h2>
                <p className="text-sm text-muted-foreground">
                    Ordenado por cambio: lo que más subió, arriba. Cada gráfica
                    usa la misma escala de 0 a 100 %; la línea punteada es la
                    primera medición.
                </p>
            </div>
            <StackedTable>
                <TableCaption className="sr-only">
                    Cada tema desde su primera medición
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Tema</TableHead>
                        <TableHead>Evolución</TableHead>
                        <TableHead className="text-right">
                            Primera medición
                        </TableHead>
                        <TableHead className="text-right">Última</TableHead>
                        <TableHead className="text-right">Cambio</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {sortByChange(series).map((one) => (
                        <DriverTrendRow
                            key={one.key}
                            series={one}
                            waves={waves}
                        />
                    ))}
                </TableBody>
            </StackedTable>
        </section>
    );
}
