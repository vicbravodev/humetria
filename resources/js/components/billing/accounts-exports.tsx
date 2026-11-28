import { ChevronDown, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { show } from '@/routes/billing/exports';

const REPORTS: {
    report: 'portfolio' | 'balances' | 'renewals';
    label: string;
}[] = [
    { report: 'portfolio', label: 'Cartera' },
    { report: 'balances', label: 'Saldos' },
    { report: 'renewals', label: 'Renovaciones' },
];

/** «Exportar ▾»: the three reports of the console as CSV files, who pays what, what is owed and what renews. */
export default function AccountsExports() {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    Exportar
                    <ChevronDown aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {REPORTS.map(({ report, label }) => (
                    <DropdownMenuItem key={report} asChild>
                        <a href={show.url(report)} download>
                            <Download aria-hidden />
                            {label} (CSV)
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
