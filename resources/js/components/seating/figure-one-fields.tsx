import { useState } from 'react';
import FigureOneResult from '@/components/seating/figure-one-result';
import YesNoField from '@/components/seating/yes-no-field';
import { figureOne } from '@/lib/seating/position-steps';
import type { StandingPosition } from '@/types';

type Errors = Partial<Record<string, string>>;

/**
 * Questions 3 to 5 of Figura 1 in the DOF's words (board LS-Puesto), and beside them the result as they are answered. The
 * result the position keeps is computed on the server when it is saved.
 */
export default function FigureOneFields({
    position,
    errors,
}: {
    position: StandingPosition | null;
    errors: Errors;
}) {
    const [answers, setAnswers] = useState({
        canWorkSeated: position?.canWorkSeated ?? null,
        hasSpaceForSeat: position?.hasSpaceForSeat ?? null,
        needsToBeNearby: position?.needsToBeNearby ?? null,
    });
    const { result, thirdNeeded } = figureOne(answers);
    const answer = (key: keyof typeof answers) => (value: boolean) =>
        setAnswers((now) => ({ ...now, [key]: value }));

    return (
        <div className="@container">
            <div className="grid gap-8 @[720px]:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid gap-5">
                    <YesNoField
                        name="can_work_seated"
                        legend="1. La persona trabajadora, ¿puede realizar sus actividades sentada?"
                        value={answers.canWorkSeated}
                        error={errors['can_work_seated']}
                        onAnswer={answer('canWorkSeated')}
                    />
                    <YesNoField
                        name="has_space_for_seat"
                        legend="2. En su lugar de trabajo, ¿existe el espacio para colocar un asiento o silla con respaldo?"
                        value={answers.hasSpaceForSeat}
                        error={errors['has_space_for_seat']}
                        onAnswer={answer('hasSpaceForSeat')}
                    />
                    <YesNoField
                        name="needs_to_be_nearby"
                        legend="3. La persona trabajadora, ¿requiere estar cerca de su lugar de trabajo?"
                        value={answers.needsToBeNearby}
                        error={errors['needs_to_be_nearby']}
                        hint={
                            thirdNeeded || result === null
                                ? undefined
                                : 'Con las dos primeras ya hay resultado; esta respuesta solo queda en el registro del puesto.'
                        }
                        onAnswer={answer('needsToBeNearby')}
                    />
                </div>
                <FigureOneResult result={result} />
            </div>
        </div>
    );
}
