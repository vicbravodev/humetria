import ChoiceField from '@/components/humetria/choice-field';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { needsProfessional } from '@/lib/action-form';
import type { ActionCatalog } from '@/types';

type Errors = Partial<Record<string, string>>;

/**
 * Which kind of action it is and at which level (num. 8.5): the kind comes from Humetría's catalog, not from the DOF, and the
 * third level, clinical care, names the professional who gives it. It is a service offered, never aimed at a person.
 */
export default function ActionKindFields({
    catalog,
    state,
    errors,
}: {
    catalog: ActionCatalog;
    state: {
        kind: string;
        level: number;
        professional: string;
        chooseKind: (kind: string) => void;
        setLevel: (level: number) => void;
        setProfessional: (professional: string) => void;
    };
    errors: Errors;
}) {
    const clinical = needsProfessional(state.level);
    const levels = catalog.levels.filter(
        (level) => needsProfessional(level.value) === clinical,
    );
    const options = catalog.kinds.map(({ value, label }) => ({ value, label }));

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="action-kind">Tipo de acción</Label>
                <Combobox
                    id="action-kind"
                    name="kind"
                    source={{ options }}
                    defaultValue={options.find(
                        (option) => option.value === state.kind,
                    )}
                    placeholder="Elige el tipo"
                    onChange={(option) => state.chooseKind(option.value)}
                />
                <p className="text-sm text-muted-foreground">
                    El catálogo es de Humetría, armado con los textos de las
                    Tablas 4 y 7 y de los numerales 8.1, 8.2 y 8.5.
                </p>
                <InputError message={errors['kind']} />
            </div>
            <ChoiceField
                name="level"
                legend="Nivel (numeral 8.5)"
                options={levels}
                value={state.level}
                onChange={state.setLevel}
                error={errors['level']}
            />
            {clinical && (
                <ChoiceField
                    name="professional"
                    legend="Quién la da"
                    options={catalog.professionals}
                    value={state.professional}
                    onChange={state.setProfessional}
                    error={errors['professional']}
                    columns={3}
                />
            )}
        </>
    );
}
