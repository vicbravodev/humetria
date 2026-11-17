import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { suggestTimezone } from '@/lib/timezones';
import type { TimezoneOption, TimezoneRules } from '@/types';

type ZoneProps = { timezones: TimezoneOption[]; timezoneRules: TimezoneRules };

/**
 * The zone a work center's day is measured in: chosen by itself from the state and the municipality, always shown, and
 * changed only by «Cambiar». A zone chosen by hand stays when the address changes; an empty field lets the server choose.
 */
export default function WorkCenterTimezoneField({
    state,
    municipality,
    saved,
    error,
}: {
    state: string | undefined;
    municipality: string;
    /** The zone the work center has; undefined for a new one. */
    saved: string | undefined;
    error: string | undefined;
}) {
    const { timezones, timezoneRules } = usePage<ZoneProps>().props;
    // A saved zone that is not the one its address suggests was chosen by hand: it is kept, in the field to change it.
    const [chosen] = useState(() =>
        saved === suggestTimezone(timezoneRules, state, municipality)
            ? undefined
            : saved,
    );
    const [changing, setChanging] = useState(chosen !== undefined);
    const suggested = suggestTimezone(timezoneRules, state, municipality);
    const current = timezones.find(
        ({ value }) => value === (chosen ?? suggested),
    );

    if (changing) {
        return (
            <div className="grid gap-2">
                <Label htmlFor="work-center-timezone">Zona horaria</Label>
                <Combobox
                    id="work-center-timezone"
                    name="timezone"
                    source={{ options: timezones }}
                    defaultValue={current}
                    placeholder="Elige una zona"
                    invalid={error !== undefined}
                    describedBy={
                        error === undefined
                            ? undefined
                            : 'work-center-timezone-error'
                    }
                />
                <InputError id="work-center-timezone-error" message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <span className="text-sm leading-none font-medium">
                Zona horaria
            </span>
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted px-4 py-3">
                <p className="text-sm">
                    {current?.label ?? suggested}
                    <span className="block text-muted-foreground">
                        Según el estado y el municipio. Con ella se miden la
                        jornada y los retardos.
                    </span>
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setChanging(true)}
                >
                    Cambiar
                </Button>
            </div>
            <InputError id="work-center-timezone-error" message={error} />
        </div>
    );
}
