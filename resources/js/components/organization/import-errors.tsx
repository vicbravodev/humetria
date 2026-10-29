import { CircleAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { EmployeeImportSummary } from '@/types';

/** Every row with a problem, as the spreadsheet numbers it, so the file can be fixed and uploaded again. */
export default function ImportErrors({
    summary,
}: {
    summary: EmployeeImportSummary;
}) {
    return (
        <Alert variant="destructive">
            <CircleAlert />
            <AlertTitle>
                No se importó nada: corrige el archivo y vuelve a subirlo
            </AlertTitle>
            <AlertDescription>
                <p>
                    {summary.errors.length === 1
                        ? '1 fila tiene'
                        : `${summary.errors.length} filas tienen`}{' '}
                    problemas, de {summary.rows} en total.
                </p>
                <ul className="mt-2 grid gap-1">
                    {summary.errors.map(({ row, messages }) => (
                        <li key={row}>
                            <span className="font-medium">Fila {row}:</span>{' '}
                            {messages.join(' ')}
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
