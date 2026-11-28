import { pesos } from '@/lib/overview';
import { countOf } from '@/lib/plural';
import type { PlatformOverviewData } from '@/types';

/** The three figures on top of «Resumen» (tablero Hoy; the design system allows three a screen): what is running, what ends soon and what is owed. Who went quiet is in «Atiende primero». */
export default function TodayFigures({
    companies,
    overdue,
}: Pick<PlatformOverviewData, 'companies' | 'overdue'>) {
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
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-3">
            {figures.map((figure) => (
                <section
                    key={figure.label}
                    className="grid content-start gap-1 card p-6"
                >
                    <h2 className="text-caption font-medium tracking-[0.02em] text-muted-foreground uppercase">
                        {figure.label}
                    </h2>
                    <p className="text-[32px] leading-10 font-semibold tracking-[-0.01em] tabular-nums">
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
