import { Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import LevelBar from '@/components/humetria/level-bar';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { instrumentsText } from '@/lib/campaigns';
import { highRiskShare, participationShare } from '@/lib/dashboard';
import { report, results } from '@/routes/tenant/campaigns';
import type { ClosedEvaluation, RiskLevelInfo } from '@/types';

/** A closed evaluation: where, when, who answered, its final score as one bar, and its results and report. */
export default function DashboardClosedRow({
    tenantSlug,
    evaluation,
    levels,
}: {
    tenantSlug: string;
    evaluation: ClosedEvaluation;
    levels: RiskLevelInfo[];
}) {
    const which = `${evaluation.workCenter}, ${evaluation.closedOn}`;

    return (
        <TableRow>
            <TableCell>
                <div className="flex flex-col">
                    <span className="font-medium">{evaluation.workCenter}</span>
                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                        {instrumentsText(evaluation.instruments)}
                    </span>
                </div>
            </TableCell>
            <TableCell>{evaluation.closedOn}</TableCell>
            <TableCell className="text-right tabular-nums">
                {participationShare(evaluation)}
            </TableCell>
            <TableCell className="whitespace-normal">
                {evaluation.final === null ? (
                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                        Menos respuestas que el mínimo para mostrarla
                    </span>
                ) : (
                    <div className="flex min-w-40 flex-col gap-1.5">
                        <LevelBar
                            counts={evaluation.final}
                            levels={levels}
                            label="Distribución por nivel de riesgo"
                            className="h-2 rounded-full"
                        />
                        <span className="text-[13px] leading-[18px] text-muted-foreground">
                            <b className="font-semibold text-foreground">
                                {highRiskShare(evaluation.final)}
                            </b>{' '}
                            en riesgo alto o muy alto
                        </span>
                    </div>
                )}
            </TableCell>
            <TableCell>
                <div className="flex justify-end gap-1.5">
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={results([tenantSlug, evaluation.id])}
                            aria-label={`Ver resultados de ${which}`}
                        >
                            Ver resultados
                        </Link>
                    </Button>
                    <Button asChild variant="ghost" size="icon">
                        <Link
                            href={report([tenantSlug, evaluation.id])}
                            aria-label={`Informe 7.7 de ${which}`}
                        >
                            <Download aria-hidden />
                        </Link>
                    </Button>
                </div>
            </TableCell>
        </TableRow>
    );
}
