import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';

/** A position whose seat goes apart and has no agreement on pauses yet (board LS-Plan), with the way to register it. */
export default function AgreementMissingRow({
    name,
    action,
}: {
    name: string;
    action: string | null;
}) {
    return (
        <TableRow>
            <TableCell className="font-medium">{name}</TableCell>
            <TableCell>
                <Badge variant="warning">Sin acuerdo</Badge>
            </TableCell>
            <TableCell>—</TableCell>
            <TableCell>
                {action !== null && (
                    <a
                        href={action}
                        className="touch-hit rounded-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Registrar acuerdo
                        <span className="sr-only"> de {name}</span>
                    </a>
                )}
            </TableCell>
        </TableRow>
    );
}
