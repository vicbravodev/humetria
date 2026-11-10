import { Link } from '@inertiajs/react';
import { FileCheck } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { show } from '@/routes/portal/vacations/statements';
import type { PendingStatement } from '@/types';

/** The way to each annual statement the person has yet to confirm they received. */
export default function PortalStatements({
    token,
    statements,
}: {
    token: string;
    statements: PendingStatement[];
}) {
    if (statements.length === 0) {
        return null;
    }

    return (
        <>
            {statements.map((statement) => (
                <Link
                    key={statement.id}
                    href={show.url({ token, statement: statement.id })}
                    className="flex items-start gap-3.5 card p-4 hover:bg-accent/50"
                >
                    <IconTile icon={FileCheck} />
                    <span className="flex flex-col gap-1">
                        <span className="text-base font-semibold">
                            Constancia de vacaciones {statement.year}
                        </span>
                        <span className="text-base text-muted-foreground">
                            Léala y confirme que la recibió.
                        </span>
                    </span>
                </Link>
            ))}
        </>
    );
}
