import { Link } from '@inertiajs/react';
import { CalendarClock, ListChecks } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { index } from '@/routes/tenant/climate/commitments';
import type { OpenCommitments } from '@/types';

/** What the last survey promised and is still open, before launching the next one. It informs; it never blocks. */
export default function OpenCommitmentsNotice({
    openCommitments,
}: {
    openCommitments: OpenCommitments;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section className="flex flex-col gap-4 card p-5 sm:p-6">
            <div className="flex items-start gap-3">
                <IconTile icon={ListChecks} />
                <div className="flex-1">
                    <h2 className="text-lg font-semibold">
                        Compromisos abiertos de la encuesta anterior
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Tienes {openCommitments.count} sin cerrar. Antes de
                        pedir opinión otra vez, conviene que la gente vea avance
                        en ellos.
                    </p>
                </div>
            </div>
            <ul className="flex flex-col gap-2">
                {openCommitments.next.map((commitment) => (
                    <li
                        key={commitment.id}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted px-4 py-3 text-sm"
                    >
                        <span>
                            {commitment.will_do}
                            <span className="text-muted-foreground">
                                {' · '}
                                {commitment.owner_name}
                            </span>
                        </span>
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <CalendarClock aria-hidden className="size-4" />
                            {describeDate(commitment.due_on)}
                        </span>
                    </li>
                ))}
            </ul>
            <div>
                <Button variant="outline" asChild>
                    <Link
                        href={index({
                            tenant: tenantSlug,
                            survey: openCommitments.surveyId,
                        })}
                    >
                        Ver los compromisos
                    </Link>
                </Button>
            </div>
        </section>
    );
}
