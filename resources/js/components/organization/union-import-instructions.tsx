import { Check } from 'lucide-react';

/** The union template's columns, as the file names them: only the first is required. */
const COLUMNS: { label: string; column: string; required: boolean }[] = [
    { label: 'ID de la empresa', column: 'numero_de_empleado', required: true },
    { label: 'Nombre completo', column: 'nombre', required: false },
    { label: 'Categoría del tabulador', column: 'categoria', required: false },
    { label: 'Nivel del tabulador', column: 'nivel', required: false },
    { label: 'Fecha de ingreso', column: 'fecha_de_ingreso', required: false },
    { label: 'Grupo', column: 'grupo', required: false },
    { label: 'Estado', column: 'estado', required: false },
    { label: 'Área', column: 'area', required: false },
    { label: 'Puesto', column: 'puesto', required: false },
];

/** How to fill a union's CSV: its columns, of which only the company's ID is required, and the rules of each. */
export default function UnionImportInstructions({
    maxRows,
}: {
    maxRows: number;
}) {
    return (
        <aside className="grid gap-4 card px-5 py-[18px]">
            <h2 className="text-sm leading-5 font-semibold">Cómo llenarlo</h2>
            <ul className="-mt-2 divide-y">
                {COLUMNS.map(({ label, column, required }) => (
                    <li key={column} className="flex items-center gap-2 py-2">
                        {required && (
                            <Check
                                aria-hidden
                                className="size-4 text-primary"
                            />
                        )}
                        <span className="flex-1 text-sm">
                            {label}
                            {required ? '' : ' (opcional)'}
                        </span>
                        <span className="text-caption text-muted-foreground">
                            {column}
                        </span>
                    </li>
                ))}
            </ul>
            <ul className="grid list-disc gap-1.5 pl-4 text-caption text-muted-foreground">
                <li>
                    Una fila por persona, hasta{' '}
                    {maxRows.toLocaleString('es-MX')}. Basta con el ID de la
                    empresa; las demás columnas pueden faltar o ir vacías.
                </li>
                <li>
                    Quien ya esté se actualiza por su ID de la empresa, sin
                    distinguir mayúsculas. Si el archivo no trae una columna,
                    ese dato no se cambia.
                </li>
                <li>
                    Categoría y nivel van juntos y se crean si aún no existen.
                    Fecha de ingreso: AAAA-MM-DD o DD/MM/AAAA. Grupo:
                    Sindicalizado o Confianza.
                </li>
                <li>Un área que no exista se crea.</li>
            </ul>
        </aside>
    );
}
