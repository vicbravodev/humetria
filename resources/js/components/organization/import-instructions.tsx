import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { index as workCentersIndex } from '@/routes/tenant/work-centers';

/** The template's columns, as the file names them. */
const COLUMNS = [
    ['Nombre completo', 'nombre'],
    ['Correo', 'correo'],
    ['Celular', 'celular'],
    ['Género', 'genero'],
    ['Centro de trabajo', 'centro_de_trabajo'],
    ['Área', 'area'],
    ['Puesto', 'puesto'],
    ['Número de empleado', 'numero_de_empleado'],
];

/** How to fill the CSV: its columns and the rules of each, with the work centers it can name. */
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
        <aside className="grid gap-4 rounded-xl border bg-card px-5 py-[18px]">
            <h2 className="text-sm leading-5 font-semibold">Cómo llenarlo</h2>
            <ul className="-mt-2 divide-y">
                {COLUMNS.map(([label, column]) => (
                    <li key={column} className="flex items-center gap-2 py-2">
                        <Check aria-hidden className="size-4 text-primary" />
                        <span className="flex-1 text-sm">{label}</span>
                        <span className="text-[13px] text-muted-foreground">
                            {column}
                        </span>
                    </li>
                ))}
            </ul>
            <ul className="grid list-disc gap-1.5 pl-4 text-[13px] leading-[18px] text-muted-foreground">
                <li>
                    Una fila por persona, hasta{' '}
                    {maxRows.toLocaleString('es-MX')}. Correo y celular: al
                    menos uno; número de empleado, opcional.
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
            </ul>
        </aside>
    );
}
