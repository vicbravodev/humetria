import ImportReady from '@/components/leave/import-ready';
import ImportRejected from '@/components/leave/import-rejected';
import { cn } from '@/lib/utils';
import type { LeaveImportSummary } from '@/types';

/** What the check found: how many periods come in, and each row that stays out with its reason. */
export default function ImportPlan({
    summary,
}: {
    summary: LeaveImportSummary;
}) {
    return (
        <div className="grid gap-6">
            <section aria-label="Resumen" className="grid grid-cols-2 gap-3">
                <div className="card px-5 py-[18px]">
                    <p className="text-sm text-muted-foreground">
                        Periodos que se cargan
                    </p>
                    <p className="text-3xl leading-[38px] font-semibold tabular-nums">
                        {summary.ready}
                    </p>
                </div>
                <div
                    className={cn(
                        'card px-5 py-[18px]',
                        summary.rejected > 0 && 'border-destructive',
                    )}
                >
                    <p
                        className={cn(
                            'text-sm',
                            summary.rejected > 0
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        Filas que se quedan fuera
                    </p>
                    <p className="text-3xl leading-[38px] font-semibold tabular-nums">
                        {summary.rejected}
                    </p>
                </div>
            </section>
            {summary.rejectedRows.length > 0 && (
                <ImportRejected summary={summary} />
            )}
            {summary.readyRows.length > 0 && (
                <ImportReady rows={summary.readyRows} />
            )}
        </div>
    );
}
