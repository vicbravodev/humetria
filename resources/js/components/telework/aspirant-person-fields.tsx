import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { serverSource } from '@/lib/server-options';
import { employees } from '@/routes/tenant/telework/teleworkers';

/** Who will telework (searched on the server among the center's active people) and how much of the time. */
export default function AspirantPersonFields({
    route,
    errors,
}: {
    route: { tenant: string; workCenter: string };
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid items-start gap-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
            <div className="grid gap-2">
                <Label htmlFor="aspirant-employee">Persona</Label>
                <Combobox
                    id="aspirant-employee"
                    name="employee_id"
                    source={serverSource(employees.url(route))}
                    placeholder="Busca por nombre"
                    invalid={errors['employee_id'] !== undefined}
                    describedBy="aspirant-employee-error"
                />
                <InputError
                    id="aspirant-employee-error"
                    role="alert"
                    message={errors['employee_id']}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="aspirant-percentage">
                    Porcentaje de teletrabajo
                </Label>
                <Input
                    id="aspirant-percentage"
                    name="telework_percentage"
                    type="number"
                    min={41}
                    max={100}
                    inputMode="numeric"
                    aria-invalid={
                        errors['telework_percentage'] === undefined
                            ? undefined
                            : true
                    }
                    aria-describedby="aspirant-percentage-hint"
                />
                <p
                    id="aspirant-percentage-hint"
                    className="text-sm text-muted-foreground"
                >
                    Más de 40 %, hasta 100 %.
                </p>
                <InputError
                    role="alert"
                    message={errors['telework_percentage']}
                />
            </div>
        </div>
    );
}
