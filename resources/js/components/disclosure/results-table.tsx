import { Eye, EyeOff } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { publish, withdraw } from '@/routes/tenant/disclosure/results';
import type { ResultsRow } from '@/types';

/** The closed evaluations and whether the people can read their results; RH publishes and withdraws them one by one. */
export default function ResultsTable({ rows }: { rows: ResultsRow[] }) {
    const tenantSlug = useTenantSlug();

    return (
        <Table>
            <TableCaption className="sr-only">
                Evaluaciones cerradas y su publicación a las personas
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Centro de trabajo</TableHead>
                    <TableHead>Cerrada el</TableHead>
                    <TableHead>Guía</TableHead>
                    <TableHead>Para las personas</TableHead>
                    <TableHead>
                        <span className="sr-only">Acción</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="font-medium whitespace-normal">
                            {row.workCenter}
                        </TableCell>
                        <TableCell>{row.closedOn}</TableCell>
                        <TableCell>{row.guide}</TableCell>
                        <TableCell>
                            {row.state === 'published' ? (
                                <Badge variant="success">
                                    Publicados el {row.publishedOn}
                                </Badge>
                            ) : (
                                <Badge variant="outline">
                                    {row.canPublish
                                        ? 'Sin publicar'
                                        : 'No aplica'}
                                </Badge>
                            )}
                        </TableCell>
                        <TableCell className="text-right">
                            {row.state === 'published' ? (
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            <EyeOff aria-hidden />
                                            Retirar
                                            <span className="sr-only">
                                                {' '}
                                                los resultados de{' '}
                                                {row.workCenter}, {row.closedOn}
                                            </span>
                                        </Button>
                                    }
                                    title="¿Retirar los resultados de la página?"
                                    description="Las personas dejan de verlos al instante. Puedes publicarlos de nuevo cuando quieras; queda constancia de ambas cosas."
                                    confirmLabel="Retirar"
                                    form={withdraw.form({
                                        tenant: tenantSlug,
                                        campaign: row.id,
                                    })}
                                    preserveScroll
                                />
                            ) : (
                                row.canPublish && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button size="sm">
                                                <Eye aria-hidden />
                                                Publicar
                                                <span className="sr-only">
                                                    {' '}
                                                    los resultados de{' '}
                                                    {row.workCenter},{' '}
                                                    {row.closedOn}
                                                </span>
                                            </Button>
                                        }
                                        title="¿Publicar los resultados para las personas?"
                                        description="Estas cifras las verá cualquier persona con el enlace de la página: cuántos cuestionarios quedan en cada nivel de riesgo, de todo el centro, sin áreas. Si el riesgo es alto, conviene tener listas las medidas antes de publicar."
                                        confirmLabel="Publicar"
                                        destructive={false}
                                        form={publish.form({
                                            tenant: tenantSlug,
                                            campaign: row.id,
                                        })}
                                        preserveScroll
                                    />
                                )
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
