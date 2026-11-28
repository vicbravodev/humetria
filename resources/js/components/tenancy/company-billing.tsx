import type { ConsolePlan } from '@/types';

/** The plan as Billing tells it: its name, since when and when it renews. What it costs and owes lives in Cuentas y cobranza. */
export default function CompanyBilling({ plan }: { plan: ConsolePlan | null }) {
    if (plan === null) {
        return (
            <p className="text-sm text-muted-foreground">
                No tiene un plan contratado.
            </p>
        );
    }

    return (
        <dl className="grid gap-4 card p-5 sm:grid-cols-3">
            {[
                ['Plan', plan.name],
                ['Desde', plan.since],
                ['Renueva', plan.renews_on],
            ].map(([label, value]) => (
                <div key={label} className="grid gap-1">
                    <dt className="text-caption text-muted-foreground">
                        {label}
                    </dt>
                    <dd className="font-medium">{value}</dd>
                </div>
            ))}
        </dl>
    );
}
