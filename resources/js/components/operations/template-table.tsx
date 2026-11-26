import { useState } from 'react';
import StackedTable from '@/components/humetria/stacked-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { count } from '@/lib/operations';
import {
    categoryLabel,
    templateLabel,
    troubledTemplates,
} from '@/lib/operations-messaging';
import type { TemplateTraffic } from '@/types';

/**
 * The WhatsApp templates as code defines them, with what each sent in 30 days (tablero Mensajería): all, or only the ones
 * with problems. Meta's approval is not known here: «Botón pendiente» means a button of it still waits for a route in the app.
 */
export default function TemplateTable({
    templates,
}: {
    templates: TemplateTraffic[];
}) {
    const [onlyTroubled, setOnlyTroubled] = useState(false);
    const shown = onlyTroubled ? troubledTemplates(templates) : templates;
    const chip = (label: string, pressed: boolean) => (
        <Button
            variant={pressed ? 'secondary' : 'outline'}
            size="sm"
            aria-pressed={pressed}
            onClick={() => setOnlyTroubled(label === 'Con problemas')}
            className={pressed ? 'border border-primary bg-accent' : ''}
        >
            {label}
        </Button>
    );

    return (
        <section className="overflow-hidden card [&_[data-slot=table-container]]:rounded-none [&_[data-slot=table-container]]:border-0">
            <header className="flex flex-wrap items-center gap-3 border-b px-6 py-4">
                <h2 className="flex-1 text-base font-semibold">
                    Plantillas de WhatsApp{' '}
                    <span className="font-normal text-muted-foreground">
                        · últimos 30 días
                    </span>
                </h2>
                {chip('Todas', !onlyTroubled)}
                {chip('Con problemas', onlyTroubled)}
            </header>
            {shown.length === 0 ? (
                <p className="px-6 py-4 text-sm text-muted-foreground">
                    Ninguna plantilla tiene problemas en los últimos 30 días.
                </p>
            ) : (
                <StackedTable className="md:[&_td]:px-3 md:[&_th]:px-3">
                    <TableHeader>
                        <TableRow className="bg-muted hover:bg-muted">
                            <TableHead>Plantilla</TableHead>
                            <TableHead>Categoría</TableHead>
                            <TableHead>En la app</TableHead>
                            <TableHead className="text-right">
                                Enviados
                                <span className="sr-only"> en 30 días</span>
                            </TableHead>
                            <TableHead className="text-right">
                                Entregados
                            </TableHead>
                            <TableHead className="text-right">
                                Rechazos
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {shown.map((template) => (
                            <TableRow key={template.key}>
                                <TableCell className="font-medium whitespace-normal max-md:w-full md:min-w-44">
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
                                    <span className="md:sr-only">
                                        {' '}
                                        enviados
                                    </span>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {count(template.delivered)}
                                    <span className="md:sr-only">
                                        {' '}
                                        entregados
                                    </span>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {count(template.rejected)}
                                    <span className="md:sr-only">
                                        {' '}
                                        rechazos
                                    </span>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </StackedTable>
            )}
        </section>
    );
}
