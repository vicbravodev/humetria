import { X } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { addCenter, removeCenter } from '@/lib/telework/documents';
import { employees } from '@/routes/tenant/telework/teleworkers';

/**
 * Who attended: the active people of the chosen center, searched on the server and added one by one. Without a
 * center there is nobody to search; changing the center starts the list again (the parent keys this by center).
 */
export default function TrainingAttendeesField({
    tenantSlug,
    centerId,
    error,
}: {
    tenantSlug: string;
    centerId: string;
    error: string | undefined;
}) {
    const [chosen, setChosen] = useState<Option[]>([]);

    return (
        <div className="grid gap-2">
            <label htmlFor="training-attendee" className="font-medium">
                Asistentes
            </label>
            {centerId === '' ? (
                <p className="text-[15px] text-muted-foreground">
                    Elige primero el centro de trabajo.
                </p>
            ) : (
                <Combobox
                    id="training-attendee"
                    name="attendee_search"
                    source={serverSource(
                        employees.url({
                            tenant: tenantSlug,
                            workCenter: centerId,
                        }),
                    )}
                    placeholder="Busca y agrega a una persona"
                    invalid={error !== undefined}
                    onChange={(person) =>
                        setChosen((current) => addCenter(current, person))
                    }
                />
            )}
            <ul
                aria-label="Asistentes elegidos"
                className="flex flex-wrap gap-2"
            >
                {chosen.map((person) => (
                    <li
                        key={person.value}
                        className="inline-flex items-center gap-1 rounded-full bg-accent py-1 pr-1 pl-3 text-sm text-primary"
                    >
                        <input
                            type="hidden"
                            name="employee_ids[]"
                            value={person.value}
                        />
                        {person.label}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            aria-label={`Quitar a ${person.label}`}
                            onClick={() =>
                                setChosen((current) =>
                                    removeCenter(current, person.value),
                                )
                            }
                        >
                            <X aria-hidden />
                        </Button>
                    </li>
                ))}
            </ul>
            <InputError role="alert" message={error} />
        </div>
    );
}
