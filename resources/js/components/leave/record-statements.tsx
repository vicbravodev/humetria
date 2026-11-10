import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dateSpan } from '@/lib/leave/format';
import { show } from '@/routes/tenant/vacations/statements';
import type { LeaveRecordStatement } from '@/types';

/** The annual statements (art. 81) of the person: when each was issued and whether they confirmed they received it. */
export default function RecordStatements({
    statements,
}: {
    statements: LeaveRecordStatement[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <ul className="flex flex-col divide-y card">
            {statements.map((statement) => (
                <li
                    key={statement.id}
                    className="flex flex-wrap items-center justify-between gap-3 p-5"
                >
                    <div>
                        <Link
                            href={show.url({
                                tenant: tenantSlug,
                                statement: statement.id,
                            })}
                            className="font-medium text-primary hover:underline"
                        >
                            Constancia del periodo {statement.year}
                        </Link>
                        <p className="text-meta text-muted-foreground">
                            Emitida el{' '}
                            {dateSpan(statement.issuedOn, statement.issuedOn)}
                        </p>
                    </div>
                    {statement.acknowledgedOn === null ? (
                        <Badge variant="warning">Sin acuse</Badge>
                    ) : (
                        <Badge variant="success">
                            Recibida el{' '}
                            {dateSpan(
                                statement.acknowledgedOn,
                                statement.acknowledgedOn,
                            )}
                        </Badge>
                    )}
                </li>
            ))}
        </ul>
    );
}
