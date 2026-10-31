import { Form } from '@inertiajs/react';
import { useState } from 'react';
import DocumentCenterField from '@/components/telework/document-center-field';
import DocumentFileField from '@/components/telework/document-file-field';
import DocumentItemsField from '@/components/telework/document-items-field';
import DocumentKindField from '@/components/telework/document-kind-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/documents';
import type { DocumentKindOption } from '@/types';

/** Publishes a document in five steps: kind, PDF, work centers, items covered, confirm (pantallas: Empleados-CSV). */
export default function DocumentUploadForm({
    tenantSlug,
    kinds,
}: {
    tenantSlug: string;
    kinds: DocumentKindOption[];
}) {
    const [kind, setKind] = useState('');
    const chosen = kinds.find((option) => option.value === kind);

    return (
        <Form
            {...store.form(tenantSlug)}
            noValidate
            resetOnSuccess
            onSuccess={() => setKind('')}
            className="flex flex-col gap-7 rounded-xl border bg-card p-6 [&>section:first-child]:border-t-0 [&>section:first-child]:pt-0"
        >
            {({ processing, errors }) => (
                <>
                    <DocumentKindField
                        kinds={kinds}
                        chosen={kind}
                        onChoose={setKind}
                        error={errors['kind']}
                    />
                    <DocumentFileField error={errors['document']} />
                    <DocumentCenterField
                        tenantSlug={tenantSlug}
                        error={errors['work_center_ids']}
                    />
                    <DocumentItemsField
                        key={kind}
                        kind={chosen}
                        error={errors['covered_items']}
                    />
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            Publicar documento
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
