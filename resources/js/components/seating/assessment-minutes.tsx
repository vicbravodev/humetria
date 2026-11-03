import { Form } from '@inertiajs/react';
import { FileCheck } from 'lucide-react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { minutes } from '@/routes/tenant/seating/assessments';
import { options } from '@/routes/tenant/seating/documents';

/** The minutes of the Comisión where this signed assessment was recorded (Quinta B), picked from the center's minutes. */
export default function AssessmentMinutes({
    assessmentId,
    workCenterId,
    current,
    canRecord,
}: {
    assessmentId: string;
    workCenterId: string;
    current: string | null;
    canRecord: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <SettingRow
            icon={FileCheck}
            title="Acta de la Comisión"
            description={
                current === null
                    ? 'Esta evaluación todavía no se asienta en un acta de recorrido.'
                    : `Asentada en: ${current}.`
            }
        >
            {canRecord && (
                <Form
                    {...minutes.form([tenantSlug, assessmentId])}
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap items-end gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid min-w-64 flex-1 gap-2">
                                <Label htmlFor="minutes-document">Acta</Label>
                                <Combobox
                                    id="minutes-document"
                                    name="document_id"
                                    source={serverSource(
                                        options.url({
                                            tenant: tenantSlug,
                                            workCenter: workCenterId,
                                        }),
                                    )}
                                    placeholder="Busca por título"
                                />
                                <InputError message={errors['document_id']} />
                            </div>
                            <Button variant="outline" disabled={processing}>
                                {processing && <Spinner />}
                                Asentar
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </SettingRow>
    );
}
