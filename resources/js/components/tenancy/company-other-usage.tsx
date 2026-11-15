import ProgressBar from '@/components/humetria/progress-bar';
import { formatAmount } from '@/lib/usage';
import type { OtherUsage } from '@/types';

/**
 * What the company used this month of the meters that only measure: campaign email and files. Nothing is refused past the
 * quota; the figure tells staff when to talk to the company about a bigger plan.
 */
export default function CompanyOtherUsage({ rows }: { rows: OtherUsage[] }) {
    return (
        <section aria-labelledby="company-other-usage" className="grid gap-4">
            <div className="grid gap-1">
                <h2
                    id="company-other-usage"
                    className="text-xl font-semibold tracking-[-0.01em]"
                >
                    Correo y archivos
                </h2>
                <p className="max-w-[620px] text-sm text-muted-foreground">
                    Solo se miden: pasar de la cuota no detiene el envío ni la
                    carga. Los archivos cuentan lo que ocupan, aunque ya no se
                    vean.
                </p>
            </div>
            <div className="grid gap-4 card p-5 md:grid-cols-2">
                {rows.map((row) => (
                    <div
                        key={row.meter}
                        data-meter={row.meter}
                        className="grid gap-2"
                    >
                        <p className="text-sm font-medium">{row.label}</p>
                        <p className="text-2xl font-semibold tracking-[-0.01em]">
                            {formatAmount(row.used, row.unit)}{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                de {formatAmount(row.quota, row.unit)}
                            </span>
                        </p>
                        <ProgressBar
                            value={row.used}
                            max={row.quota}
                            label={`${row.label} del mes`}
                        />
                    </div>
                ))}
            </div>
        </section>
    );
}
