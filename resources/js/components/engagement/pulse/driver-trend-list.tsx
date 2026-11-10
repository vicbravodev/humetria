import TrendCard from '@/components/engagement/pulse/trend-card';
import type { TrendSeries } from '@/types';

/** The ten themes, each as its anchor question across the waves. */
export default function DriverTrendList({ series }: { series: TrendSeries[] }) {
    return (
        <section aria-labelledby="trend-drivers" className="grid gap-4">
            <h2 id="trend-drivers" className="text-xl font-semibold">
                Por tema
            </h2>
            <div className="grid gap-4 lg:grid-cols-2">
                {series.map((one) => (
                    <TrendCard key={one.key} series={one} />
                ))}
            </div>
        </section>
    );
}
