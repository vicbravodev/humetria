import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
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

/** Columns a file may add; without them, what a person already has is left as it is. */
const OPTIONAL_COLUMNS = [
    ['Categoría del tabulador', 'categoria'],
    ['Nivel del tabulador', 'nivel'],
    ['Fecha de ingreso', 'fecha_de_ingreso'],
    ['Grupo', 'grupo'],
    ['Estado', 'estado'],
    ['Jefe directo', 'jefe'],
];

/** How to fill the CSV: its columns and the rules of each, with the work centers it can name. */
export default function ImportInstructions({
    maxRows,
    hasWorkCenters,
}: {
    maxRows: number;
    hasWorkCenters: boolean;
}) {
    const tenantSlug = useTenantSlug();
    return (
        <aside className="grid gap-4 card px-5 py-[18px]">
            <h2 className="text-sm leading-5 font-semibold">Cómo llenarlo</h2>
            <ul className="-mt-2 divide-y">
                {COLUMNS.map(([label, column]) => (
                    <li key={column} className="flex items-center gap-2 py-2">
                        <Check aria-hidden className="size-4 text-primary" />
                        <span className="flex-1 text-sm">{label}</span>
                        <span className="text-caption text-muted-foreground">
                            {column}
                        </span>
                    </li>
                ))}
            </ul>
            <h3 className="text-sm leading-5 font-semibold">
                Columnas opcionales
            </h3>
            <ul className="-mt-2 divide-y">
                {OPTIONAL_COLUMNS.map(([label, column]) => (
                    <li key={column} className="flex items-center gap-2 py-2">
                        <span className="flex-1 text-sm">{label}</span>
                        <span className="text-caption text-muted-foreground">
                            {column}
                        </span>
                    </li>
                ))}
            </ul>
            <ul className="grid list-disc gap-1.5 pl-4 text-caption text-muted-foreground">
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
                    Categoría y nivel van juntos. Fecha de ingreso: AAAA-MM-DD o
                    DD/MM/AAAA. Grupo: Sindicalizado o Confianza. Si el archivo
                    no trae una de estas columnas, ese dato no se cambia.
                </li>
                <li>
                    Jefe: su número de empleado o, si no tiene, su correo; puede
                    ser otra fila del archivo, en cualquier orden. Vacío, la
                    persona queda sin jefe.
                </li>
                <li>
                    Quien ya esté (por número de empleado o, si no tiene, por
                    correo) se actualiza.
                </li>
            </ul>
        </aside>
    );
}
