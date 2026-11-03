import { Form } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { resolve } from '@/routes/tenant/seating/reports';

/** RH writes how the report was resolved; that closes it. */
export default function ReportResolveForm({ reportId }: { reportId: string }) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...resolve.form([tenantSlug, reportId])}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Textarea
                        name="resolution"
                        rows={2}
                        aria-label="Cómo se resolvió"
                        placeholder="Cómo se resolvió"
                    />
                    <InputError
                        message={errors['resolution'] ?? errors['status']}
                    />
                    <Button size="sm" className="w-fit" disabled={processing}>
                        {processing && <Spinner />}
                        Marcar como resuelto
                    </Button>
                </>
            )}
        </Form>
    );
}
