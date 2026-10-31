import { Form, Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import ArcMeter from '@/components/humetria/arc-meter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { instrumentsText } from '@/lib/campaigns';
import DashboardKpi from '@/components/tenancy/dashboard-kpi';
import { closingKpi, missingKpi } from '@/lib/dashboard';
import { resend, show } from '@/routes/tenant/campaigns';
import type { ActiveEvaluation } from '@/types';

/**
 * The evaluation in course that closes soonest (pantalla Panel): who answered of how many on the arc, what is
 * missing, the days left, and the way to follow it up or remind whoever is missing.
 */
export default function DashboardActiveCard({
    tenantSlug,
    evaluation,
    canManage,
}: {
    tenantSlug: string;
    evaluation: ActiveEvaluation;
    canManage: boolean;
}) {
    const answered = `${evaluation.completed} de ${evaluation.target} contestaron`;

    return (
        <article
            aria-labelledby="active-evaluation"
            className="flex flex-col gap-5 rounded-xl border bg-card p-6"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h2
                        id="active-evaluation"
                        className="text-lg leading-[26px] font-semibold"
                    >
                        {evaluation.workCenter}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {instrumentsText(evaluation.instruments)} · cierra el{' '}
                        {evaluation.closesOn}
                    </p>
                </div>
                <Badge>En curso</Badge>
            </div>
            <div className="flex flex-wrap items-end gap-7">
                <ArcMeter
                    value={evaluation.completed}
                    max={evaluation.target}
                    label={answered}
                >
                    <span className="text-[39px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        {evaluation.completed}
                    </span>
                    <span className="max-w-[98px] text-[13px] leading-4 text-muted-foreground">
                        de {evaluation.target} contestaron
                    </span>
                </ArcMeter>
                <div className="flex flex-[1_1_180px] flex-col gap-3.5 pb-1.5">
                    <DashboardKpi kpi={missingKpi(evaluation)} />
                    <DashboardKpi kpi={closingKpi(evaluation.daysLeft)} />
                </div>
            </div>
            <div className="flex flex-wrap items-center justify-end gap-2.5 border-t pt-4">
                <Button asChild variant="outline" size="sm">
                    <Link href={show([tenantSlug, evaluation.id])}>
                        Ver avance
                    </Link>
                </Button>
                {canManage && (
                    <Form
                        {...resend.form([tenantSlug, evaluation.id])}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Send aria-hidden />
                                )}
                                Recordar a quien falta
                            </Button>
                        )}
                    </Form>
                )}
            </div>
        </article>
    );
}
