import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import RosterArea from '@/components/telework/roster-area';
import RosterHome from '@/components/telework/roster-home';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MARITAL_STATUSES } from '@/lib/telework/roster';
import type { HomeAddress } from '@/types';

type Errors = Partial<Record<string, string>>;

/** The data of numeral 5.1 that RH gives to move an aspirant into the roster; the job profile is the employee's own job title. */
export default function RosterFields({
    jobTitle,
    home,
    states,
    errors,
}: {
    jobTitle: string;
    /** The first workplace validated: the home starts from it. */
    home: HomeAddress | null;
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
                    message={errors['contact_phone']}
                />
            </div>
            <RosterHome home={home} states={states} errors={errors} />
            <RosterArea
                name="no_equipment_reason"
                label="Equipo entregado"
                hint="Opcional si ya registraste el equipo de la persona. Si no se le entregó ninguno, escribe por qué."
                errors={errors}
            />
        </div>
    );
}
