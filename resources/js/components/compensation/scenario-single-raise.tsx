import NumberStepper from '@/components/humetria/number-stepper';
import type { RaiseDraft } from '@/lib/compensation/raise-draft';
import { peopleHint, raiseSuffix } from '@/lib/compensation/raise-draft';
import { stepRaise } from '@/lib/compensation/scenario-draft';

/** The one raise of an average payroll: it has one group, so the raise is not asked per group. */
export default function ScenarioSingleRaise({
    draft,
    onChange,
    people,
    errors,
}: {
    draft: RaiseDraft;
    onChange: (patch: Partial<RaiseDraft>) => void;
    people: number | undefined;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid items-start gap-5 sm:max-w-[calc(50%-10px)]">
            <NumberStepper
                id="scenario-unionized_raise"
                name="unionized_raise"
                label="Aumento"
                value={draft.unionized}
                suffix={raiseSuffix(draft.unit)}
                hint={peopleHint(people)}
                error={errors['unionized_raise']}
                onChange={(unionized) => onChange({ unionized })}
                onStep={(direction) =>
                    onChange({
                        unionized: stepRaise(
                            draft.unionized,
                            direction,
                            draft.unit,
                        ),
                    })
                }
            />
        </div>
    );
}
