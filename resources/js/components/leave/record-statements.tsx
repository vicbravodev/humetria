import { Badge } from '@/components/ui/badge';
import { dateSpan } from '@/lib/leave/format';
import type { LeaveRecordStatement } from '@/types';

/** The annual statements (art. 81) of the person: when each was issued and whether they confirmed they received it. */
export default function RecordStatements({
    statements,
}: {
    statements: LeaveRecordStatement[];
}) {
    return (
        <ul className="flex flex-col divide-y card">
            {statements.map((statement) => (
                <li
                    key={statement.id}
                    className="flex flex-wrap items-center justify-between gap-3 p-5"
                >
                    <div>
                        <p className="font-medium">
                            Constancia del periodo {statement.year}
                        </p>
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
