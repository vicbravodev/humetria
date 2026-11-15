import { Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { index as balances } from '@/routes/tenant/vacations/balances';
import type { LeaveSummary } from '@/types';

/**
 * The one notice of the overview: what is missing for the days to be given, in a line each with the link that fixes it
 * (no start date: nobody is given days; statements without acknowledgment).
 */
export default function SummaryNotice({ summary }: { summary: LeaveSummary }) {
    const tenantSlug = useTenantSlug();
    const lines = [
        {
            show: summary.missingStartDate > 0,
            text: `${countOf(summary.missingStartDate, 'persona no tiene', 'personas no tienen')} fecha de ingreso: sin ella no se les otorgan días.`,
            link: 'Completar',
            tab: 'missing',
        },
        {
            show: summary.statementsUnacknowledged > 0,
            text: `${countOf(summary.statementsUnacknowledged, 'constancia anual sin acuse', 'constancias anuales sin acuse')}.`,
            link: 'Ver quiénes',
            tab: 'statements',
        },
    ].filter((line) => line.show);

    if (lines.length === 0) {
        return null;
    }

    return (
        <div
            role="status"
            data-slot="notice"
            className="flex gap-3 rounded-xl bg-warning px-5 py-3.5 text-warning-foreground"
        >
            <CircleAlert aria-hidden className="mt-0.5 size-5 shrink-0" />
            <ul className="flex min-w-0 flex-1 flex-col gap-1.5 text-meta">
                {lines.map((line) => (
                    <li key={line.tab} className="flex flex-wrap gap-x-2">
                        <span>{line.text}</span>
                        <Link
                            href={balances.url(tenantSlug, {
                                query: { tab: line.tab },
                            })}
                            className="touch-hit font-semibold underline underline-offset-4"
                        >
                            {line.link} →
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
