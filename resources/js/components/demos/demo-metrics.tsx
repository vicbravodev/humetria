import { Skeleton } from '@/components/ui/skeleton';
import { firstResponseText, unattendedText } from '@/lib/demo-requests';
import type { DemoMetrics as Metrics } from '@/types';

/**
 * The four figures of the funnel: what waits, how soon the team answers, how many became a company and where most
 * came from. They arrive after the page shows; until then each holds its place.
 */
export default function DemoMetrics({
    metrics,
}: {
    metrics: Metrics | undefined;
}) {
    const tiles =
        metrics === undefined
            ? null
            : [
                  {
                      title: 'Nuevas',
                      figure: String(metrics.new),
                      caption: unattendedText(metrics.unattended),
                  },
                  {
                      title: 'Tiempo de primera respuesta',
                      figure: firstResponseText(metrics.first_response_hours),
                      caption: 'Mediana, últimos 90 días',
                  },
                  {
                      title: 'Conversión a empresa',
                      figure:
                          metrics.conversion === null
                              ? 'Sin dato'
                              : `${metrics.conversion} %`,
                      caption: 'Últimos 90 días',
                  },
                  {
                      title: 'Fuente principal',
                      figure: metrics.source?.label ?? 'Sin dato',
                      caption:
                          metrics.source === null
                              ? 'Ninguna solicitud dijo de dónde llegó'
                              : `${metrics.source.share} % de las solicitudes, 90 días`,
                  },
              ];

    return (
        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,200px),1fr))] gap-4">
            {tiles === null
                ? [0, 1, 2, 3].map((tile) => (
                      <Skeleton key={tile} className="h-[118px] rounded-xl" />
                  ))
                : tiles.map((tile) => (
                      <section key={tile.title} className="grid gap-1 card p-5">
                          <h2 className="text-caption text-muted-foreground">
                              {tile.title}
                          </h2>
                          <p className="truncate text-3xl font-semibold tracking-[-0.01em] tabular-nums">
                              {tile.figure}
                          </p>
                          <p className="text-sm text-muted-foreground">
                              {tile.caption}
                          </p>
                      </section>
                  ))}
        </div>
    );
}
