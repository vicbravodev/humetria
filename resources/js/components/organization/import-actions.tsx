import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { importButtonText, importSummaryText } from '@/lib/employees';
import type { EmployeeImportSummary } from '@/types';

/** The foot of the upload: where it stands in one sentence, then checking the file and, once it is ready, importing it. */
export default function ImportActions({
    summary,
    processing,
    submitAs,
}: {
    summary: EmployeeImportSummary | undefined;
    processing: boolean;
    submitAs: (confirmed: boolean) => () => void;
}) {
    const ready = summary?.status === 'ready' ? summary : null;

    return (
        <div className="flex flex-wrap items-center justify-end gap-3 border-t pt-4">
            <p role="status" className="mr-auto text-sm text-muted-foreground">
                {ready !== null
                    ? importSummaryText(ready)
                    : summary === undefined
                      ? 'Revisamos el archivo completo antes de importar: nunca se guarda a medias.'
                      : 'Corrige el archivo y vuelve a revisarlo: se importa completo o nada.'}
            </p>
            <Button
                type="submit"
                variant={ready === null ? 'default' : 'outline'}
                disabled={processing}
                onClick={submitAs(false)}
            >
                {processing && <Spinner />}
                Revisar archivo
            </Button>
            {ready !== null && (
                <Button
                    type="submit"
                    disabled={processing}
                    onClick={submitAs(true)}
                >
                    {importButtonText(ready.rows)}
                </Button>
            )}
        </div>
    );
}
