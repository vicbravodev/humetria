import { GripVertical, UserPlus } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import OrgChangeManager from '@/components/organization/org-change-manager';
import { Button } from '@/components/ui/button';
import type { ManagerRef } from '@/hooks/use-org-editor';
import { placeText } from '@/lib/org-chart/labels';
import { asOrgNode } from '@/lib/org-chart/tray';
import type { OrgNode, UnassignedPerson } from '@/types';

/** A row of the tray: dragged onto a manager in the canvas, or given one with «Asignar jefe». */
export default function OrgTrayPerson({
    person,
    onStart,
    editor,
}: {
    person: UnassignedPerson;
    onStart: (person: UnassignedPerson) => void;
    editor: {
        message: string | null;
        processing: boolean;
        assign: (
            person: OrgNode,
            manager: ManagerRef | null,
        ) => Promise<boolean>;
    };
}) {
    return (
        <li
            draggable
            onDragStart={(event) => {
                event.dataTransfer.setData('text/plain', person.id);
                event.dataTransfer.effectAllowed = 'move';
                onStart(person);
            }}
            data-slot="org-tray-person"
            className="flex cursor-grab items-center gap-2 card p-2 active:cursor-grabbing"
        >
            <GripVertical
                aria-hidden
                className="size-4 shrink-0 text-muted-foreground"
            />
            <Initials name={person.name} />
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-semibold">
                    {person.name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    {[person.job_title, placeText(person)]
                        .filter((part) => part !== '')
                        .join(' · ')}
                </span>
            </span>
            <OrgChangeManager
                trigger={
                    <Button
                        variant="ghost"
                        size="icon"
                        title="Asignar jefe"
                        aria-label={`Asignar jefe a ${person.name}`}
                    >
                        <UserPlus />
                    </Button>
                }
                person={asOrgNode(person)}
                manager={null}
                editor={editor}
            />
        </li>
    );
}
