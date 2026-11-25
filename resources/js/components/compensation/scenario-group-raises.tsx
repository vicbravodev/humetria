import NumberStepper from '@/components/humetria/number-stepper';
import type { RaiseDraft } from '@/lib/compensation/raise-draft';
import {
    peopleHint,
    raiseSuffix,
    stepTrusted,
    trustedHint,
} from '@/lib/compensation/raise-draft';
import { stepRaise } from '@/lib/compensation/scenario-draft';

/** The raise of each group of the roster: the unionized and the trusted, who copy the unionized one unless they have their own. */
export default function ScenarioGroupRaises({
    draft,
    onChange,
    groups,
    errors,
}: {
    draft: RaiseDraft;
    onChange: (patch: Partial<RaiseDraft>) => void;
    groups: { unionized: number; trusted: number } | undefined;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid items-start gap-5 sm:grid-cols-2">
            <NumberStepper
                id="scenario-unionized_raise"
                name="unionized_raise"
                label="Sindicalizados"
                value={draft.unionized}
                suffix={raiseSuffix(draft.unit)}
                hint={peopleHint(groups?.unionized)}
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
            <NumberStepper
                id="scenario-trusted_raise"
                name="trusted_raise"
                label="Confianza"
                value={draft.trusted}
                suffix={raiseSuffix(draft.unit)}
                hint={trustedHint(draft, groups?.trusted)}
                placeholder={draft.unionized}
                error={errors['trusted_raise']}
                onChange={(trusted) => onChange({ trusted })}
                onStep={(direction) =>
                    onChange({ trusted: stepTrusted(draft, direction) })
                }
            />
        </div>
    );
}
