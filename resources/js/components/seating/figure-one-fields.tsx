import { GitBranch } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import YesNoField from '@/components/seating/yes-no-field';
import type { StandingPosition } from '@/types';

type Errors = Partial<Record<string, string>>;

/** Questions 3 to 5 of Figura 1, in the DOF's words; the result is derived on the server and shown once saved. */
export default function FigureOneFields({
    position,
    errors,
}: {
    position: StandingPosition | null;
    errors: Errors;
}) {
    return (
        <SettingRow
            icon={GitBranch}
            title="Figura 1: dónde va el asiento"
            description="Contesta las tres preguntas del flujograma de las Disposiciones. El resultado (A, B o C) lo calcula la plataforma."
        >
            <div className="grid gap-5">
                <YesNoField
                    name="can_work_seated"
                    legend="La persona trabajadora, ¿puede realizar sus actividades sentada?"
                    value={position?.canWorkSeated ?? null}
                    error={errors['can_work_seated']}
                />
                <YesNoField
                    name="has_space_for_seat"
                    legend="En su lugar de trabajo, ¿existe el espacio para colocar un asiento o silla con respaldo?"
                    value={position?.hasSpaceForSeat ?? null}
                    error={errors['has_space_for_seat']}
                />
                <YesNoField
                    name="needs_to_be_nearby"
                    legend="La persona trabajadora, ¿requiere estar cerca de su lugar de trabajo?"
                    value={position?.needsToBeNearby ?? null}
                    error={errors['needs_to_be_nearby']}
                />
            </div>
        </SettingRow>
    );
}
