import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/input-error';
import RosterArea from '@/components/telework/roster-area';
import WorkplaceFields from '@/components/telework/workplace-fields';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MARITAL_STATUSES } from '@/lib/telework/roster';

type Errors = Partial<Record<string, string>>;

/** The data of numeral 5.1 that RH gives to move an aspirant into the roster; the job profile is the employee's own job title. */
export default function RosterFields({
    jobTitle,
    states,
    errors,
}: {
    jobTitle: string;
    states: Record<string, string>;
    errors: Errors;
}) {
    return (
        <div className="grid gap-5">
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="roster-job-title">Puesto</Label>
                    <Input id="roster-job-title" value={jobTitle} readOnly />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="roster-marital">Estado civil</Label>
                    <Combobox
                        id="roster-marital"
                        name="marital_status"
                        source={{ options: MARITAL_STATUSES }}
                        placeholder="Elige una opción"
                        invalid={errors['marital_status'] !== undefined}
                        describedBy="roster-marital-error"
                    />
                    <InputError
                        id="roster-marital-error"
                        role="alert"
                        message={errors['marital_status']}
                    />
                </div>
            </div>
            <RosterArea
                name="activities"
                label="Actividades que realiza"
                errors={errors}
            />
            <div className="grid gap-2 sm:max-w-64">
                <Label htmlFor="roster-phone">Teléfono de contacto</Label>
                <Input
                    id="roster-phone"
                    name="contact_phone"
                    type="tel"
                    maxLength={30}
                    aria-invalid={
                        errors['contact_phone'] === undefined ? undefined : true
                    }
                    aria-describedby="roster-phone-error"
                />
                <InputError
                    id="roster-phone-error"
                    role="alert"
                    message={errors['contact_phone']}
                />
            </div>
            <fieldset className="grid gap-4 rounded-xl border p-4">
                <legend className="px-1 font-medium">
                    Domicilio de la persona
                </legend>
                <WorkplaceFields index={null} states={states} errors={errors} />
            </fieldset>
            <RosterArea
                name="no_equipment_reason"
                label="Equipo entregado"
                hint="Opcional si ya registraste el equipo de la persona. Si no se le entregó ninguno, escribe por qué."
                errors={errors}
            />
        </div>
    );
}
