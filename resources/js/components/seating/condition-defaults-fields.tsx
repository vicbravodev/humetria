import { SlidersHorizontal } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import type { ConditionOption, StandingPosition } from '@/types';

type Errors = Partial<Record<string, string>>;

const FIELDS: Record<
    string,
    { name: string; value: keyof StandingPosition | undefined }
> = {
    standing_time: {
        name: 'default_standing_time',
        value: 'defaultStandingTime',
    },
    posture_change: {
        name: 'default_posture_change',
        value: 'defaultPostureChange',
    },
    surface: { name: 'default_surface', value: 'defaultSurface' },
    movement_space: {
        name: 'default_movement_space',
        value: 'defaultMovementSpace',
    },
    pauses: { name: 'default_pauses', value: 'defaultPauses' },
};

/** The five conditions of the position that start each person's assessment (the footwear and discomfort are the person's own). */
export default function ConditionDefaultsFields({
    conditions,
    position,
    errors,
}: {
    conditions: ConditionOption[];
    position: StandingPosition | null;
    errors: Errors;
}) {
    return (
        <SettingRow
            icon={SlidersHorizontal}
            title="Condiciones de las personas"
            description="Lo que casi todas las personas de este puesto comparten: la evaluación de cada una empieza con esto y la Comisión lo confirma o lo corrige."
        >
            <div className="grid gap-6">
                {conditions.map((condition) => {
                    const field = FIELDS[condition.key];
                    const current =
                        field?.value === undefined
                            ? null
                            : position?.[field.value];

                    return (
                        <fieldset key={condition.key} className="grid gap-2">
                            <legend className="mb-1 font-medium">
                                {condition.letter}) {condition.title}
                            </legend>
                            {condition.options.map((option) => (
                                <label
                                    key={option.points}
                                    className={CHOICE_CLASS}
                                >
                                    <input
                                        type="radio"
                                        name={field?.name ?? condition.key}
                                        value={option.points}
                                        defaultChecked={
                                            current === option.points
                                        }
                                        className="size-5 shrink-0 accent-primary"
                                    />
                                    <span>
                                        {option.text}
                                        <span className="ml-2 text-caption text-muted-foreground">
                                            {option.points}{' '}
                                            {option.points === 1
                                                ? 'punto'
                                                : 'puntos'}
                                        </span>
                                    </span>
                                </label>
                            ))}
                            <InputError message={errors[field?.name ?? '']} />
                        </fieldset>
                    );
                })}
            </div>
        </SettingRow>
    );
}
