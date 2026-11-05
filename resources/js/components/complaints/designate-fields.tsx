import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/complaints/centers';

/** Who, among the people offered, and for which scope: the whole company or one work center searched in the server. */
export default function DesignateFields({
    people,
    errors,
}: {
    people: Option[];
    errors: Partial<Record<string, string>>;
}) {
    const tenantSlug = useTenantSlug();
    const [wholeCompany, setWholeCompany] = useState(true);

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="handler-person">Persona</Label>
                <Combobox
                    // A new list of people is a new field: what was chosen in the other one does not carry over.
                    key={people.map((person) => person.value).join()}
                    id="handler-person"
                    name="user_id"
                    source={{ options: people }}
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
                    <Label htmlFor="handler-center">Centro de trabajo</Label>
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
        </>
    );
}
