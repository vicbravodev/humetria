import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import TrendSince from '@/components/engagement/pulse/trend-since';
import ColumnChart from '@/components/humetria/column-chart';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    baselineOf,
    changeSinceFirst,
    columnsOf,
} from '@/lib/engagement/trend';
import { trend } from '@/routes/tenant/pulse';
import type { TrendPoint } from '@/types';

/**
 * «Cómo vamos» beside the next pulse: the engagement index of the latest waves as columns from zero, how far it moved
 * since the first of them, and the way to the whole trend. Company level only.
 */
export default function HowWeAreCard({ points }: { points: TrendPoint[] }) {
    const tenantSlug = useTenantSlug();
    const baseline = baselineOf(points);

    return (
        <section
            aria-labelledby="how-we-are"
            className="flex min-w-0 flex-col gap-4 card p-5 sm:p-6"
        >
            <div className="flex flex-col gap-1">
                <h2 id="how-we-are" className="text-lg font-semibold">
                    Cómo vamos
                </h2>
                <p className="text-sm text-muted-foreground">
                    Índice de compromiso
                </p>
            </div>
            {points.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Aparece cuando una encuesta cierra con al menos 5 respuestas
                    en cada pregunta del índice.
                </p>
            ) : (
                <ColumnChart
                    label="Índice de compromiso de las últimas encuestas"
                    columns={columnsOf(points, 'percent')}
                    {...(baseline === undefined ? {} : { baseline })}
                />
            )}
            <TrendSince change={changeSinceFirst(points)} />
            <Link
                href={trend(tenantSlug)}
                className="touch-hit inline-flex items-center gap-1.5 self-start font-medium text-primary underline-offset-4 hover:underline"
            >
                Ver tendencia
                <ArrowRight aria-hidden className="size-4" />
            </Link>
        </section>
    );
}
