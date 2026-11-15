import { Replace } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import RowMenu from '@/components/humetria/row-menu';
import AssignmentSelect from '@/components/leave/assignment-select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { LeavePolicyOption } from '@/types';

/**
 * One sentence of whom a policy goes to («Sindicalizados siguen Contrato colectivo»); «Cambiar», in the menu of the line,
 * opens the choice of its policy. A viewer reads the sentence and has no menu.
 */
export default function AssignmentLine({
    text,
    label,
    target,
    value,
    options,
    canManage,
    note,
}: {
    text: string;
    /** Whom it is for, in the name of the menu and the dialog. */
    label: string;
    /** The target as the server takes it: the scope with its group or grade. */
    target: Record<string, string>;
    value: string | null;
    options: LeavePolicyOption[];
    canManage: boolean;
    /** A pill or a warning next to the sentence. */
    note?: ReactNode;
}) {
    const [changing, setChanging] = useState(false);

    return (
        <li className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
            <span className="min-w-0 font-medium">{text}</span>
            {note}
            {canManage && (
                <span className="ml-auto">
                    <RowMenu
                        label={`Acciones de ${label}`}
                        items={[
                            {
                                label: 'Cambiar',
                                icon: Replace,
                                onSelect: () => setChanging(true),
                            },
                        ]}
                    />
                    <Dialog open={changing} onOpenChange={setChanging}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Política de {label.toLowerCase()}
                                </DialogTitle>
                                <DialogDescription>
                                    Elige la política que sigue. «Ley» le quita
                                    la suya.
                                </DialogDescription>
                            </DialogHeader>
                            <AssignmentSelect
                                label={`Política de ${label.toLowerCase()}`}
                                target={target}
                                value={value}
                                options={options}
                                disabled={false}
                                onChosen={() => setChanging(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </span>
            )}
        </li>
    );
}
