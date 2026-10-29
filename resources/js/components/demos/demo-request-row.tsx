import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { whatsappUrl } from '@/lib/whatsapp-url';
import type { DemoRequestRow as DemoRequest } from '@/types';

const LINK =
    'text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

export default function DemoRequestRow({ request }: { request: DemoRequest }) {
    return (
        <TableRow>
            <TableCell>
                <p>{request.name}</p>
                <p className="text-sm text-muted-foreground">
                    {request.company}
                </p>
            </TableCell>
            <TableCell>
                <p>
                    <a href={`mailto:${request.email}`} className={LINK}>
                        {request.email}
                    </a>
                </p>
                <p className="text-sm">
                    <a
                        href={whatsappUrl(request.whatsapp)}
                        target="_blank"
                        rel="noreferrer"
                        className={LINK}
                    >
                        {request.whatsapp}
                    </a>
                </p>
            </TableCell>
            <TableCell>
                <p>{request.profile}</p>
                <p className="text-sm text-muted-foreground">
                    {request.workforce} trabajadores
                </p>
                <div className="mt-1 flex flex-wrap gap-1">
                    {request.tools.map((tool) => (
                        <Badge key={tool} variant="secondary">
                            {tool}
                        </Badge>
                    ))}
                </div>
            </TableCell>
            <TableCell className="max-w-md min-w-64 whitespace-normal">
                {request.message ?? (
                    <span className="text-muted-foreground">Sin mensaje</span>
                )}
            </TableCell>
            <TableCell className="tabular-nums">
                {request.received_at}
            </TableCell>
        </TableRow>
    );
}
