import { Form } from '@inertiajs/react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/work-centers';
import { store } from '@/routes/tenant/complaints/handlers';

/** Designates a person of the company to handle complaints: for all of it, or for the work center searched. */
export default function DesignateForm({
    candidates,
}: {
    candidates: Option[];
}) {
    const tenantSlug = useTenantSlug();
    const [wholeCompany, setWholeCompany] = useState(true);

    return (
        <Form
            {...store.form(tenantSlug)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-col gap-4 card p-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="handler-person">Persona</Label>
                        <Combobox
                            id="handler-person"
                            name="user_id"
                            source={{ options: candidates }}
                            placeholder="Elige a quién designar"
                            invalid={errors['user_id'] !== undefined}
                        />
                        <InputError message={errors['user_id']} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="handler-whole-company"
                            checked={wholeCompany}
                            onCheckedChange={(checked) =>
                                setWholeCompany(checked === true)
                            }
                        />
                        <Label htmlFor="handler-whole-company">
                            Atiende quejas de toda la empresa
                        </Label>
                    </div>
                    {!wholeCompany && (
                        <div className="grid gap-2">
                            <Label htmlFor="handler-center">
                                Centro de trabajo
                            </Label>
                            <Combobox
                                id="handler-center"
                                name="work_center_id"
                                source={serverSource(options.url(tenantSlug))}
                                placeholder="Busca un centro"
                                invalid={errors['work_center_id'] !== undefined}
                            />
                            <InputError message={errors['work_center_id']} />
                        </div>
                    )}
                    <Button className="self-start" disabled={processing}>
                        {processing && <Spinner />}
                        Designar
                    </Button>
                </>
            )}
        </Form>
    );
}
