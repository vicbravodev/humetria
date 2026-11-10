import { Form, router, usePage } from '@inertiajs/react';
import { FileUp } from 'lucide-react';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import ImportPlan from '@/components/leave/import-plan';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { chosenFile, importSummaryText } from '@/lib/leave/import';
import { plan, store } from '@/routes/tenant/vacations/import';

/**
 * Uploads the CSV to be checked; once something can be loaded, the same file is sent again to confirm, and the server plans it
 * again from the file. The page keeps its state across the check, so the chosen file is still there to confirm.
 */
export default function ImportForm() {
    const tenantSlug = useTenantSlug();
    const summary = usePage().flash.leave_import;
    const confirm = () => {
        const field = document.getElementById('leave-import-file');
        const file = chosenFile(
            field instanceof HTMLInputElement ? field.files?.[0] : null,
        );

        if (file !== null) {
            router.post(
                store.url(tenantSlug),
                { file },
                { forceFormData: true, preserveScroll: true },
            );
        }
    };

    return (
        <Form
            {...plan.form(tenantSlug)}
            options={{ preserveState: true, preserveScroll: true }}
            className="grid gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <FileDropZone
                            id="leave-import-file"
                            name="file"
                            accept=".csv,text/csv"
                            icon={FileUp}
                            title="Arrastra aquí tu CSV o elígelo"
                            hint="Con las columnas de la plantilla, una fila por persona y periodo."
                            invalid={errors['file'] !== undefined}
                            describedBy="leave-import-file-error"
                        />
                        <InputError
                            id="leave-import-file-error"
                            message={errors['file']}
                        />
                    </div>
                    {summary !== undefined && <ImportPlan summary={summary} />}
                    <div className="flex flex-wrap items-center justify-end gap-3 border-t pt-4">
                        <p
                            role="status"
                            className="mr-auto text-sm text-muted-foreground"
                        >
                            {summary === undefined
                                ? 'Revisamos el archivo completo antes de cargar: no se escribe nada hasta que confirmes.'
                                : importSummaryText(summary)}
                        </p>
                        <Button
                            type="submit"
                            variant={
                                summary !== undefined && summary.ready > 0
                                    ? 'outline'
                                    : 'default'
                            }
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Revisar archivo
                        </Button>
                        {summary !== undefined && summary.ready > 0 && (
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={confirm}
                            >
                                Cargar los saldos
                            </Button>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
