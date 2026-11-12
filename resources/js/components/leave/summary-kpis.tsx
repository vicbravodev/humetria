import { Link } from '@inertiajs/react';
import { CalendarClock, Hourglass, Inbox } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { index as balances } from '@/routes/tenant/vacations/balances';
import { index as requests } from '@/routes/tenant/vacations/requests';
import type { LeaveSummary } from '@/types';

/**
 * The three numbers of the overview, one leading and two behind it: what waits for RH (with what waits for the managers),
 * the windows about to close and the days still owed. Who is out is a line of the header, not a card.
 */
export default function SummaryKpis({ summary }: { summary: LeaveSummary }) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="leave-summary"
            className="flex flex-col gap-3"
        >
            <h2 id="leave-summary" className="sr-only">
                Resumen de vacaciones
            </h2>
            <div className="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-3 card p-6">
                    <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Inbox aria-hidden className="size-4" />
                        Esperan tu respuesta
                    </p>
                    <p className="text-[40px] leading-[48px] font-semibold tracking-[-0.03em] tabular-nums">
                        {summary.escalated}
                    </p>
                    <p className="text-meta text-muted-foreground">
                        {summary.pending > 0 ? (
                            <Link
                                href={requests.url(tenantSlug, {
                                    query: { tab: 'waiting' },
                                })}
                                className="touch-hit underline-offset-4 hover:underline"
                            >
                                {summary.pending} esperan a su jefe
                            </Link>
                        ) : (
                            'Ninguna espera a un jefe'
                        )}
                    </p>
                </div>
                <div className="flex flex-col gap-3 card p-6">
                    <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <CalendarClock aria-hidden className="size-4" />
                        Ventanas por cerrar
                    </p>
                    <p className="text-[32px] leading-10 font-semibold tracking-[-0.02em] tabular-nums">
                        {summary.windowsClosing}
                    </p>
                    <p className="text-meta text-muted-foreground">
                        Periodos con plazo por vencer o vencido
                    </p>
                </div>
                <div className="flex flex-col gap-3 card p-6">
                    <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Hourglass aria-hidden className="size-4" />
                        Días por disfrutar
                    </p>
                    <p className="text-[32px] leading-10 font-semibold tracking-[-0.02em] tabular-nums">
                        {new Intl.NumberFormat('es-MX').format(
                            summary.liabilityDays,
                        )}
                    </p>
                    <p className="text-meta text-muted-foreground">
                        {countOf(summary.periods, 'periodo', 'periodos')} ·{' '}
                        {summary.prescribableDays > 0 ? (
                            <Link
                                href={balances.url(tenantSlug)}
                                className="touch-hit underline-offset-4 hover:underline"
                            >
                                {countOf(
                                    summary.prescribableDays,
                                    'día podría prescribir',
                                    'días podrían prescribir',
                                )}
                            </Link>
                        ) : (
                            'ninguno por prescribir'
                        )}
                    </p>
                </div>
            </div>
            {summary.prescribableDays > 0 && (
                <p className="text-meta text-muted-foreground">
                    Siguen en el saldo: oponer la prescripción es decisión de la
                    empresa.
                </p>
            )}
        </section>
    );
}
