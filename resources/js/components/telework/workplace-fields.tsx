import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/input-error';
import WorkplaceField from '@/components/telework/workplace-field';
import { Label } from '@/components/ui/label';
import { workplaceField } from '@/lib/workplace-field';

/** The address of a workplace, in the parts a work center has (pantalla: Empleados-Alta). */
export default function WorkplaceFields({
    index,
    states,
    errors,
}: {
    /** Its place in a list of workplaces, or null when it is the only one being sent. */
    index: number | null;
    states: Record<string, string>;
    errors: Partial<Record<string, string>>;
}) {
    const field = { index, errors };
    const state = workplaceField(index, 'state');
    const stateId = `workplace-${index ?? 'new'}-state`;

    return (
        <div className="grid gap-4">
            <div className="grid items-start gap-4 sm:grid-cols-[1fr_7rem_7rem]">
                <WorkplaceField
                    {...field}
                    field="street"
                    label="Calle"
                    required
                    maxLength={150}
                />
                <WorkplaceField
                    {...field}
                    field="exterior_number"
                    label="Núm. exterior"
                    required
                    maxLength={20}
                />
                <WorkplaceField
                    {...field}
                    field="interior_number"
                    label="Núm. interior"
                    maxLength={20}
                />
            </div>
            <div className="grid items-start gap-4 sm:grid-cols-[1fr_9rem]">
                <WorkplaceField
                    {...field}
                    field="neighborhood"
                    label="Colonia"
                    required
                    maxLength={120}
                />
                <WorkplaceField
                    {...field}
                    field="postal_code"
                    label="Código postal"
                    required
                    inputMode="numeric"
                    maxLength={5}
                />
            </div>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <WorkplaceField
                    {...field}
                    field="municipality"
                    label="Municipio o alcaldía"
                    required
                    maxLength={120}
                />
                <div className="grid gap-2">
                    <Label htmlFor={stateId}>Estado</Label>
                    <Combobox
                        id={stateId}
                        name={state.name}
                        source={{
                            options: Object.entries(states).map(
                                ([value, label]) => ({ value, label }),
                            ),
                        }}
                        placeholder="Elige un estado"
                        invalid={errors[state.error] !== undefined}
                        describedBy={`${stateId}-error`}
                    />
                    <InputError
                        id={`${stateId}-error`}
                        role="alert"
                        message={errors[state.error]}
                    />
                </div>
            </div>
        </div>
    );
}
