import { useState } from 'react';
import type { ReactNode } from 'react';
import Combobox from '@/components/humetria/combobox';
import OrgEditMessage from '@/components/organization/org-edit-message';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ManagerRef } from '@/hooks/use-org-editor';
import { managerOf } from '@/lib/org-chart/panel';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/org-chart/people';
import type { ChoiceOption, OrgNode } from '@/types';

const NOBODY = { value: '', label: 'Sin jefe directo' };

/**
 * «Cambiar jefe»: the way to assign a manager without dragging (keyboard, touch). The manager is searched on the
 * server, without the person and whoever reports to them; if the server still says no, it says why, without alarm.
 */
export default function OrgChangeManager({
    person,
    manager,
    editor,
    trigger,
}: {
    person: OrgNode;
    /** The button that opens it: «Cambiar jefe» in the panel, «Asignar jefe» in a row of the tray. */
    trigger: ReactNode;
    /** Their manager now, to show it chosen. */
    manager: OrgNode | null;
    editor: {
        message: string | null;
        processing: boolean;
        assign: (
            person: OrgNode,
            manager: ManagerRef | null,
        ) => Promise<boolean>;
    };
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);
    const [chosen, setChosen] = useState<ChoiceOption | null>(null);
    const source = serverSource(
        `${options.url(tenantSlug)}?${new URLSearchParams({ except: person.id }).toString()}`,
        manager === null ? [] : [NOBODY],
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>{trigger}</PopoverTrigger>
            <PopoverContent align="end" className="grid w-[340px] gap-3 p-4">
                <p className="text-base font-semibold">
                    Cambiar jefe de {person.name}
                </p>
                <div className="grid gap-1.5">
                    <Label htmlFor={`manager-of-${person.id}`}>
                        Jefe directo
                    </Label>
                    <Combobox
                        id={`manager-of-${person.id}`}
                        name="manager_id"
                        source={source}
                        defaultValue={
                            manager === null
                                ? undefined
                                : { value: manager.id, label: manager.name }
                        }
                        placeholder="Elige a su jefe directo"
                        onChange={setChosen}
                    />
                </div>
                {editor.message !== null && (
                    <OrgEditMessage message={editor.message} />
                )}
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => setOpen(false)}>
                        Cancelar
                    </Button>
                    <Button
                        disabled={chosen === null || editor.processing}
                        onClick={() => {
                            if (chosen !== null) {
                                void editor
                                    .assign(person, managerOf(chosen))
                                    .then((saved) => setOpen(!saved));
                            }
                        }}
                    >
                        {editor.processing && <Spinner />}
                        Asignar
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
