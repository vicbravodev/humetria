import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import { serverSource } from '@/lib/server-options';
import type { ChoiceOption, EmployeeChoices } from '@/types';

/**
 * The direct manager, searched on the server among the active people; `except` leaves out that person and whoever
 * reports to them, who cannot be their manager. The first option, before typing, leaves the field empty.
 */
export default function EmployeeManagerField({
    error,
    choices,
    except,
    manager,
    name = 'manager_id',
    label = 'Jefe directo',
    help = 'Déjalo vacío si nadie está por encima.',
}: {
    error: string | undefined;
    choices: EmployeeChoices;
    except: string | undefined;
    manager: ChoiceOption | null;
    name?: string;
    label?: string;
    help?: string;
}) {
    const url =
        except === undefined
            ? choices.managersUrl
            : `${choices.managersUrl}?${new URLSearchParams({ except }).toString()}`;

    return (
        <div className="grid gap-2">
            <EmployeeChoiceField
                name={name}
                label={label}
                placeholder="Opcional"
                source={serverSource(url, [
                    { value: '', label: 'Sin jefe directo' },
                    ...(manager === null ? [] : [manager]),
                ])}
                defaultValue={manager ?? undefined}
                error={error}
            />
            <p className="text-caption text-muted-foreground">{help}</p>
        </div>
    );
}
