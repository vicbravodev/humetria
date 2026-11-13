import { Form, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useRef } from 'react';
import ImportActions from '@/components/organization/import-actions';
import ImportErrors from '@/components/organization/import-errors';
import ImportFileField from '@/components/organization/import-file-field';
import ImportChanges from '@/components/organization/import-changes';
import ImportWarnings from '@/components/organization/import-warnings';
import ImportStats from '@/components/organization/import-stats';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/employees/import';

/**
 * Uploads the CSV to be checked first; once it is valid, the same file is sent again with `confirm` to import it.
 * The page keeps its state across the check, so the chosen file is still there to confirm.
 */
export default function ImportForm({
    aside,
}: {
    /** How to fill the file, beside it. */
    aside: ReactNode;
}) {
    const tenantSlug = useTenantSlug();
    const summary = usePage().flash.import;
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
            className="grid gap-8"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        ref={confirm}
                        type="hidden"
                        name="confirm"
                        defaultValue="0"
                    />
                    <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                        <div className="grid gap-6">
                            <ImportFileField error={errors['file']} />
                            {summary !== undefined && (
                                <ImportStats summary={summary} />
                            )}
                            {summary?.status === 'ready' &&
                                summary.warnings !== undefined && (
                                    <ImportWarnings
                                        warnings={summary.warnings}
                                    />
                                )}
                            {summary?.status === 'ready' &&
                                summary.changes !== undefined && (
                                    <ImportChanges changes={summary.changes} />
                                )}
                            {summary?.status === 'invalid' && (
                                <ImportErrors summary={summary} />
                            )}
                        </div>
                        {aside}
                    </div>
                    <ImportActions
                        summary={summary}
                        processing={processing}
                        submitAs={submitAs}
                    />
                </>
            )}
        </Form>
    );
}
