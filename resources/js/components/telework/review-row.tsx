import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import { describeDate } from '@/lib/calendar-date';
import { show } from '@/routes/tenant/telework/reviews';
import type { ReviewRow as Row } from '@/types';

/** One checklist waiting for the Comisión: who sent it, for which place, and since when (pantalla: Canalizacion). */
export default function ReviewRow({
    tenantSlug,
    row,
}: {
    tenantSlug: string;
    row: Row;
}) {
    const sent =
        row.submittedOn === null ? undefined : describeDate(row.submittedOn);

    return (
        <li>
            <Link
                href={show({ tenant: tenantSlug, application: row.id })}
                className="flex items-center gap-3 rounded-xl border bg-card px-[22px] py-5 transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <Initials name={row.person} className="size-10 text-[13px]" />
                <div className="flex min-w-0 flex-1 flex-col">
                    <p className="font-medium">{row.person}</p>
                    <p className="text-[13px] leading-[18px] text-muted-foreground">
                        {row.jobTitle} · {row.workplace}
                    </p>
                    <p className="text-[13px] leading-[18px] text-muted-foreground">
                        {row.section === 1
                            ? 'Primera lista'
                            : 'Revisión periódica'}
                        {sent !== undefined && ` · enviada el ${sent}`}
                    </p>
                </div>
                <ChevronRight
                    aria-hidden
                    className="size-5 text-muted-foreground"
                />
            </Link>
        </li>
    );
}
