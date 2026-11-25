import StackedTable from '@/components/humetria/stacked-table';
import { Badge } from '@/components/ui/badge';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { count } from '@/lib/operations';
import { categoryLabel, templateLabel } from '@/lib/operations-messaging';
import type { TemplateTraffic } from '@/types';

/**
 * The WhatsApp templates as code defines them, with what each sent in 30 days. Meta's approval is not known here: «Botón
 * pendiente» means a button of it still waits for a route in the app.
 */
export default function TemplateTable({
    templates,
}: {
    templates: TemplateTraffic[];
}) {
    return (
        <section className="card">
            <header className="border-b px-5 py-4">
                <h2 className="text-base font-semibold">
                    Plantillas de WhatsApp
                </h2>
                <p className="text-sm text-muted-foreground">
                    Las que la app define, con lo que mandó en 30 días. Su
                    aprobación se revisa en Meta.
                </p>
            </header>
            <StackedTable>
                <TableHeader>
                    <TableRow>
                        <TableHead>Plantilla</TableHead>
                        <TableHead>Categoría</TableHead>
                        <TableHead>En la app</TableHead>
                        <TableHead className="text-right">Enviados</TableHead>
                        <TableHead className="text-right">Entregados</TableHead>
                        <TableHead className="text-right">Rechazos</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {templates.map((template) => (
                        <TableRow key={template.key}>
                            <TableCell className="font-medium max-md:w-full">
                                {templateLabel(template.key)}
                            </TableCell>
                            <TableCell>
                                {categoryLabel(template.category)}
                            </TableCell>
                            <TableCell>
                                {template.pendingInCode ? (
                                    <Badge variant="warning">
                                        Botón pendiente
                                    </Badge>
                                ) : (
                                    <Badge variant="success">Lista</Badge>
                                )}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {count(template.total)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {count(template.delivered)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {count(template.rejected)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </StackedTable>
        </section>
    );
}
