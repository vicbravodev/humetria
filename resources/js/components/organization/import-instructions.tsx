import { Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { template } from '@/routes/tenant/employees/import';
import { index as workCentersIndex } from '@/routes/tenant/work-centers';

/** How to fill the CSV: the template, its columns and the work centers it can name. */
export default function ImportInstructions({
    tenantSlug,
    maxRows,
    hasWorkCenters,
}: {
    tenantSlug: string;
    maxRows: number;
    hasWorkCenters: boolean;
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
                    Centro de trabajo, escrito como en{' '}
                    <Link
                        href={workCentersIndex(tenantSlug)}
                        className="text-primary underline underline-offset-4"
                    >
                        Centros de trabajo
                    </Link>
                    {hasWorkCenters
                        ? '.'
                        : ': aún no hay ninguno; da de alta uno primero.'}
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
