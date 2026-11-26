import { pesos } from '@/lib/overview';
import { countOf } from '@/lib/plural';
import type { PlatformOverviewData } from '@/types';

/** The four figures on top of «Resumen» (tablero Hoy): what is running, what ends soon, what is owed and who went quiet. */
export default function TodayFigures({
    companies,
    overdue,
    inactive,
}: Pick<PlatformOverviewData, 'companies' | 'overdue' | 'inactive'>) {
    const figures = [
        {
            label: 'Empresas activas',
            value: String(companies.active),
            caption: `${companies.all} en total · ${companies.trial} en prueba`,
        },
        {
            label: 'Prueba por vencer',
            value: String(companies.trial_ending),
            caption: 'en los próximos 7 días',
        },
        {
            label: 'Por cobrar vencido',
            value: pesos(overdue.cents),
            caption: countOf(overdue.accounts.length, 'cuenta', 'cuentas'),
        },
        {
            label: 'Sin entrar en 30 días',
            value: String(inactive),
            caption: 'empresas en riesgo de baja',
        },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {figures.map((figure) => (
                <section key={figure.label} className="grid gap-1 card p-5">
                    <h2 className="text-caption font-medium tracking-[0.02em] text-muted-foreground uppercase">
                        {figure.label}
                    </h2>
                    <p className="text-3xl font-semibold tracking-[-0.01em] tabular-nums">
                        {figure.value}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {figure.caption}
                    </p>
                </section>
            ))}
        </div>
    );
}
