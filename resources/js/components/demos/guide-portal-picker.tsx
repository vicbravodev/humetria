import { router } from '@inertiajs/react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import { Button } from '@/components/ui/button';
import { serverSource } from '@/lib/server-options';
import { portal } from '@/routes/demo-room/session';
import { options } from '@/routes/demo-room/session/people';

/**
 * For the Vacaciones tour: a person of the session's center and «Abrir su portal», which leaves the panel for that person's
 * portal in this same tab, with a one-use link never shown (an Inertia location, so a visit, not a fetch).
 */
export default function GuidePortalPicker() {
    const [person, setPerson] = useState<string | null>(null);

    return (
        <div className="flex flex-col gap-2">
            <label htmlFor="demo-portal-person" className="text-sm font-medium">
                Persona del público
            </label>
            <Combobox
                id="demo-portal-person"
                name="employee"
                source={serverSource(options.url())}
                placeholder="Busca a una persona…"
                onChange={(option) => setPerson(option.value)}
            />
            <Button
                size="sm"
                variant="outline"
                className="self-start max-md:h-11"
                disabled={person === null}
                onClick={() => {
                    if (person !== null) {
                        router.post(portal.url(person));
                    }
                }}
            >
                Abrir su portal
            </Button>
        </div>
    );
}
