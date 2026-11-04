import { Copy, Ellipsis, History, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ActionDialogs from '@/components/interventions/action-dialogs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { isOpen } from '@/lib/action-form';
import type {
    ActionTool,
    ProgramAction,
    ProgramRow,
    ProgramTools,
} from '@/types';

/**
 * What is done to an action: record a step, edit it, copy it to other rows, cancel it (with a reason) or read its history.
 * Whoever only reads the Programa gets just the history. Nothing is deleted.
 */
export default function ActionMenu({
    action,
    campaignId,
    rows,
    tools,
}: {
    action: ProgramAction;
    campaignId: string;
    rows: ProgramRow[];
    tools: ProgramTools | null;
}) {
    const [opened, setOpened] = useState<ActionTool | null>(null);
    const writable = tools !== null && isOpen(action.status.value);

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Opciones de ${action.title}`}
                        className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-52">
                    {writable && (
                        <DropdownMenuItem
                            onSelect={() => setOpened('progress')}
                        >
                            <Plus aria-hidden />
                            Registrar avance
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem onSelect={() => setOpened('history')}>
                        <History aria-hidden />
                        Ver historial
                    </DropdownMenuItem>
                    {writable && (
                        <>
                            <DropdownMenuItem
                                onSelect={() => setOpened('edit')}
                            >
                                <Pencil aria-hidden />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => setOpened('copy')}
                            >
                                <Copy aria-hidden />
                                Copiar a otras filas
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setOpened('cancel')}
                            >
                                <Trash2 aria-hidden />
                                Cancelar la acción
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <ActionDialogs
                opened={opened}
                onClose={(open) => {
                    if (!open) {
                        setOpened(null);
                    }
                }}
                action={action}
                campaignId={campaignId}
                rows={rows}
                tools={tools}
            />
        </>
    );
}
