import { Link } from '@inertiajs/react';
import { ChevronRight, FileCheck } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { show } from '@/routes/portal/vacations/statements';
import type { PendingStatement } from '@/types';

/** What waits for the person's confirmation: the way to each annual statement they have yet to confirm they received. */
export default function PortalStatements({
    slug,
    statements,
}: {
    slug: string;
    statements: PendingStatement[];
}) {
    if (statements.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="leave-statements" className="grid gap-3">
            <h2
                id="leave-statements"
                className="flex items-center gap-2 text-xl font-semibold"
            >
                Espera su confirmación
                <span className="min-w-6 rounded-full bg-primary px-2 text-center text-sm leading-6 font-semibold text-primary-foreground">
                    {statements.length}
                </span>
            </h2>
            {statements.map((statement) => (
                <Link
                    key={statement.id}
                    href={show.url({ tenant: slug, statement: statement.id })}
                    className="flex min-h-16 items-center gap-3.5 card p-4 hover:bg-accent/50"
                >
                    <IconTile icon={FileCheck} />
                    <span className="flex min-w-0 flex-1 flex-col">
                        <span className="text-base font-semibold">
                            Constancia de vacaciones {statement.year}
                        </span>
                        <span className="text-base text-muted-foreground">
                            Léala y confirme que la recibió
                        </span>
                    </span>
                    <ChevronRight
                        aria-hidden
                        className="size-5 shrink-0 text-muted-foreground"
                    />
                </Link>
            ))}
        </section>
    );
}
