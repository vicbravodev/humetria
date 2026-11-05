import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { FixedAlternative } from '@/lib/compensation/report-charts';
import { create } from '@/routes/tenant/compensation/scenarios';

/** «Para la negociación»: the fixed amount that costs the same in salary, and «Probar un monto fijo» for whoever manages it. */
export default function ReportFixedTip({
    alternative,
    raise,
    canTry,
}: {
    alternative: FixedAlternative;
    /** "5": the percent of the sindicalizados it is weighed against. */
    raise: string;
    canTry: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <aside
            aria-labelledby="report-fixed-tip"
            className="flex break-inside-avoid flex-col gap-2 self-start rounded-xl border bg-muted p-5"
        >
            <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                Para la negociación
            </p>
            <h3 id="report-fixed-tip" className="font-semibold">
                Un monto fijo reparte distinto
            </h3>
            <p className="text-sm">
                Con el mismo costo en salario, {alternative.amountText} al mes
                para cada sindicalizado daría más a quien gana menos:{' '}
                {alternative.lowest} recibiría {alternative.moreText} más que
                con el {raise} %.
            </p>
            {canTry && (
                <Button
                    variant="outline"
                    asChild
                    className="mt-1 self-start print:hidden"
                >
                    <Link
                        href={create(tenantSlug, { query: alternative.query })}
                    >
                        Probar un monto fijo
                    </Link>
                </Button>
            )}
        </aside>
    );
}
