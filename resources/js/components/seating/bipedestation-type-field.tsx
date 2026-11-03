import InputError from '@/components/humetria/input-error';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import type { TypeOption } from '@/types';

/** The type of bipedestación of Quinta C IX (static, dynamic, prolonged or a mix), one choice card each. */
export default function BipedestationTypeField({
    types,
    value,
    error,
}: {
    types: TypeOption[];
    value: string | undefined;
    error: string | undefined;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 font-medium">Tipo de bipedestación</legend>
            <div className="grid gap-2 sm:grid-cols-2">
                {types.map((type) => (
                    <label key={type.value} className={CHOICE_CLASS}>
                        <input
                            type="radio"
                            name="bipedestation_type"
                            value={type.value}
                            defaultChecked={value === type.value}
                            className="size-5 shrink-0 accent-primary"
                        />
                        {type.label}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
