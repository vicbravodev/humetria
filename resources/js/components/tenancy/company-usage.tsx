import ProgressBar from '@/components/humetria/progress-bar';
import UsageGrantDialog from '@/components/tenancy/usage-grant-dialog';
import UsageHistory from '@/components/tenancy/usage-history';
import { formatTokens, usageLine, usagePercent } from '@/lib/usage';
import type { Paginated, UsageMonth, UsageSummary } from '@/types';

/**
 * Humi's tokens for the company this month: used, held while an answer is being written, and the quota (the plan's plus what a
 * person added), with the months before. The superadmin adds tokens with a reason; support reads.
 */
export default function CompanyUsage({
    companyId,
    usage,
    history,
    canManage,
}: {
    companyId: string;
    usage: UsageSummary;
    history: Paginated<UsageMonth>;
    canManage: boolean;
}) {
    return (
        <section aria-labelledby="company-usage" className="grid gap-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="grid gap-1">
                    <h2
                        id="company-usage"
                        className="text-xl font-semibold tracking-[-0.01em]"
                    >
                        Consumo de Humi
                    </h2>
                    <p className="max-w-[620px] text-sm text-muted-foreground">
                        Los tokens del mes ({usage.period_label}). Sin cuota,
                        Humi deja de responder; nada más del producto se afecta.
                    </p>
                </div>
                {canManage && <UsageGrantDialog companyId={companyId} />}
            </div>
            <div data-usage className="grid gap-3 card p-5">
                <p className="text-2xl font-semibold tracking-[-0.01em]">
                    {formatTokens(usage.used)}{' '}
                    <span className="text-sm font-normal text-muted-foreground">
                        de {formatTokens(usage.quota)} tokens usados
                    </span>
                </p>
                <ProgressBar
                    value={usage.used + usage.reserved}
                    max={usage.quota}
                    label={`Tokens de Humi, ${usagePercent(usage)} % del mes`}
                />
                <p className="text-sm text-muted-foreground">
                    {usageLine(usage)}
                    {usage.granted > 0 &&
                        ` Incluye ${formatTokens(usage.granted)} agregados a mano.`}
                </p>
            </div>
            <UsageHistory months={history} />
        </section>
    );
}
