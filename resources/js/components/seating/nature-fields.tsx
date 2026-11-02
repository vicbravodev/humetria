import { Factory } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import YesNoField from '@/components/seating/yes-no-field';
import { Textarea } from '@/components/ui/textarea';
import type { StandingPosition } from '@/types';

type Errors = Partial<Record<string, string>>;

/** Industrial centers only: whether the nature of the work allows seating, and why not, in writing (criterio C-03). */
export default function NatureFields({
    position,
    errors,
}: {
    position: StandingPosition | null;
    errors: Errors;
}) {
    return (
        <SettingRow
            icon={Factory}
            title="Naturaleza del trabajo"
            description="En un centro industrial la ley pide el asiento donde la naturaleza del trabajo lo permite. Si no lo permite, deja la razón por escrito."
        >
            <div className="grid gap-4">
                <YesNoField
                    name="nature_allows"
                    legend="La naturaleza del trabajo de este puesto, ¿permite el asiento?"
                    value={position?.natureAllows ?? null}
                    error={errors['nature_allows']}
                />
                <div className="grid gap-2">
                    <label htmlFor="nature-reason" className="font-medium">
                        Razón (obligatoria si no lo permite)
                    </label>
                    <Textarea
                        id="nature-reason"
                        name="nature_reason"
                        rows={3}
                        defaultValue={position?.natureReason ?? ''}
                    />
                    <InputError message={errors['nature_reason']} />
                </div>
            </div>
        </SettingRow>
    );
}
