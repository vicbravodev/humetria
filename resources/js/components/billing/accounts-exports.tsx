import { Button } from '@/components/ui/button';
import { show } from '@/routes/billing/exports';

const REPORTS: {
    report: 'portfolio' | 'balances' | 'renewals';
    label: string;
}[] = [
    { report: 'portfolio', label: 'Cartera' },
    { report: 'balances', label: 'Saldos' },
    { report: 'renewals', label: 'Renovaciones' },
];

/** The three reports of the console as CSV files: who pays what, what is owed and what renews. */
export default function AccountsExports() {
    return (
        <div
            role="group"
            aria-label="Descargar reportes en CSV"
            className="flex flex-wrap items-center gap-2"
        >
            {REPORTS.map(({ report, label }) => (
                <Button key={report} variant="outline" asChild>
                    <a href={show.url(report)} download>
                        {label} (CSV)
                    </a>
                </Button>
            ))}
        </div>
    );
}
