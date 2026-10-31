import { importStats } from '@/lib/employees';
import { cn } from '@/lib/utils';
import type { EmployeeImportSummary } from '@/types';

export default function ImportStats({
    summary,
}: {
    summary: EmployeeImportSummary;
}) {
    return (
        <section
            aria-label="Resumen"
            className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3"
        >
            {importStats(summary).map((stat) => (
                <div
                    key={stat.label}
                    className={cn(
                        'rounded-xl border bg-card px-5 py-[18px]',
                        stat.alert === true && 'border-destructive',
                    )}
                >
                    <p
                        className={cn(
                            'text-sm leading-5',
                            stat.alert === true
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {stat.label}
                    </p>
                    <p className="text-3xl leading-[38px] font-semibold tabular-nums">
                        {stat.value.toLocaleString('es-MX')}
                    </p>
                    {stat.hint !== undefined && (
                        <p className="text-[13px] leading-[18px] text-muted-foreground">
                            {stat.hint}
                        </p>
                    )}
                </div>
            ))}
        </section>
    );
}
