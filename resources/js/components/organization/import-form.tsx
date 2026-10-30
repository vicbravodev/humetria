import { Form, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import ImportErrors from '@/components/organization/import-errors';
import ImportFileField from '@/components/organization/import-file-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { importButtonText, importSummaryText } from '@/lib/employees';
import { store } from '@/routes/tenant/employees/import';

/**
 * Uploads the CSV to be checked first; once it is valid, the same file is sent again with `confirm` to import it.
 * The page keeps its state across the check, so the chosen file is still there to confirm.
 */
export default function ImportForm({ tenantSlug }: { tenantSlug: string }) {
    const summary = usePage().flash.import;
    const ready = summary?.status === 'ready' ? summary : null;
    const invalid = summary?.status === 'invalid' ? summary : null;
    const confirm = useRef<HTMLInputElement>(null);
    // Which button sent the form: the hidden field is set before the form reads its values.
    const submitAs = (confirmed: boolean) => () => {
        if (confirm.current !== null) {
            confirm.current.value = confirmed ? '1' : '0';
        }
    };

    return (
        <Form
            {...store.form(tenantSlug)}
            options={{ preserveState: true, preserveScroll: true }}
            className="grid gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        ref={confirm}
                        type="hidden"
                        name="confirm"
                        defaultValue="0"
                    />
                    <ImportFileField error={errors['file']} />
                    {invalid !== null && <ImportErrors summary={invalid} />}
                    {ready !== null && (
                        <p
                            role="status"
                            className="rounded-xl border bg-card p-4"
                        >
                            {importSummaryText(ready)}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
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
                </>
            )}
        </Form>
    );
}
