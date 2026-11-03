import { ClipboardList } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import BipedestationTypeField from '@/components/seating/bipedestation-type-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { StandingPosition, TypeOption } from '@/types';

type Errors = Partial<Record<string, string>>;

const invalid = (error: string | undefined) =>
    error === undefined ? undefined : true;

/** The name of the position, what is done standing, how (type and minutes) and how many people at once. */
export default function PositionBasicsFields({
    position,
    types,
    errors,
}: {
    position: StandingPosition | null;
    types: TypeOption[];
    errors: Errors;
}) {
    return (
        <SettingRow
            icon={ClipboardList}
            title="El puesto"
            description="Qué se hace de pie, de qué manera y cuántas personas lo ocupan a la vez."
        >
            <div className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="position-name">Nombre del puesto</Label>
                    <Input
                        id="position-name"
                        name="name"
                        defaultValue={position?.name ?? ''}
                        aria-invalid={invalid(errors['name'])}
                    />
                    <InputError message={errors['name']} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="position-tasks">
                        Tareas que realiza de pie
                    </Label>
                    <Textarea
                        id="position-tasks"
                        name="tasks"
                        rows={3}
                        defaultValue={position?.tasks ?? ''}
                        aria-invalid={invalid(errors['tasks'])}
                    />
                    <InputError message={errors['tasks']} />
                </div>
                <BipedestationTypeField
                    types={types}
                    value={position?.type}
                    error={errors['bipedestation_type']}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="position-minutes">
                            Minutos continuos de pie
                        </Label>
                        <Input
                            id="position-minutes"
                            name="continuous_minutes"
                            type="number"
                            min={0}
                            max={1440}
                            inputMode="numeric"
                            defaultValue={position?.continuousMinutes ?? ''}
                            aria-invalid={invalid(errors['continuous_minutes'])}
                        />
                        <p className="text-sm text-muted-foreground">
                            Más de 180 minutos (tres horas) es bipedestación
                            prolongada.
                        </p>
                        <InputError message={errors['continuous_minutes']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="position-people">
                            Personas a la vez en el puesto
                        </Label>
                        <Input
                            id="position-people"
                            name="max_people_at_once"
                            type="number"
                            min={1}
                            inputMode="numeric"
                            defaultValue={position?.maxPeopleAtOnce ?? ''}
                            aria-invalid={invalid(errors['max_people_at_once'])}
                        />
                        <InputError message={errors['max_people_at_once']} />
                    </div>
                </div>
            </div>
        </SettingRow>
    );
}
