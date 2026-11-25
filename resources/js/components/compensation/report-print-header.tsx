import { useEffect, useState } from 'react';
import Logo from '@/components/humetria/logo';

const ISSUED = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'long',
    timeZone: 'America/Mexico_City',
});

/**
 * The head of the report on paper: the Humetría logo, the company and the scenario, and the day it was issued, at the top of the
 * first sheet (it is only on paper). The same words go to the top margin of the following sheets: the browser cannot repeat an
 * element there, so they travel as custom properties of the page that the `report` named page reads (app.css).
 */
export default function ReportPrintHeader({
    company,
    scenario,
}: {
    company: string | null;
    scenario: string;
}) {
    const title = company === null ? scenario : `${company} · ${scenario}`;
    const [issued] = useState(() => `Emitido el ${ISSUED.format(new Date())}`);

    useEffect(() => {
        const root = document.documentElement.style;
        root.setProperty('--print-report-title', JSON.stringify(title));
        root.setProperty('--print-report-issued', JSON.stringify(issued));

        return () => {
            root.removeProperty('--print-report-title');
            root.removeProperty('--print-report-issued');
        };
    }, [title, issued]);

    return (
        <div
            data-slot="print-letterhead"
            className="hidden items-center justify-between gap-6 border-b pb-3 print:flex"
        >
            <Logo className="h-6" />
            <p className="flex flex-col text-right text-meta">
                <span className="font-semibold">{title}</span>
                <span className="text-muted-foreground">{issued}</span>
            </p>
        </div>
    );
}
