import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { template } from '@/routes/tenant/employees/import';

/** How to fill the CSV: the template, its columns and the work centers it can name. */
export default function ImportInstructions({
    tenantSlug,
    maxRows,
    workCenters,
}: {
    tenantSlug: string;
    maxRows: number;
    workCenters: string[];
}) {
    return (
        <section className="grid gap-4 rounded-xl border bg-card p-6">
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                Cómo llenarlo
            </h2>
            <ol className="grid list-decimal gap-2 pl-5 text-muted-foreground">
                <li>
                    Descarga la plantilla y llena una fila por persona, hasta{' '}
                    {maxRows.toLocaleString('es-MX')}. Correo y celular: al
                    menos uno.
                </li>
                <li>
                    Género: Mujer, Hombre u Otro. Un área que no exista se crea.
                </li>
                <li>
                    Centro de trabajo, escrito como en Humetría:{' '}
                    {workCenters.length === 0
                        ? 'aún no hay ninguno; da de alta uno primero.'
                        : workCenters.join(', ')}
                    .
                </li>
                <li>
                    Quien ya esté (por número de empleado o, si no tiene, por
                    correo) se actualiza.
                </li>
            </ol>
            <Button variant="outline" asChild className="w-fit">
                <a href={template.url(tenantSlug)} download>
                    <Download />
                    Descargar plantilla
                </a>
            </Button>
        </section>
    );
}
