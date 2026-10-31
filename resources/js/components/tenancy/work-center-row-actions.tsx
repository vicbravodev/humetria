import { Link } from '@inertiajs/react';
import { Archive, ArchiveRestore, Ellipsis, Pencil } from 'lucide-react';
import { useState } from 'react';
import ArchiveWorkCenterDialog from '@/components/tenancy/archive-work-center-dialog';
import WorkCenterDialog from '@/components/tenancy/work-center-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { restore, update } from '@/routes/tenant/work-centers';
import type { MexicanStateOption, WorkCenterRow } from '@/types';

type Action = 'edit' | 'archive';

/**
 * What can be done with a work center, behind one "…" button: an active one is edited or archived (apart, in the
 * destructive color); an archived one is only restored.
 */
export default function WorkCenterRowActions({
    tenantSlug,
    workCenter,
    states,
}: {
    tenantSlug: string;
    workCenter: WorkCenterRow;
    states: MexicanStateOption[];
}) {
    const [open, setOpen] = useState<Action | null>(null);
    const dialog = (action: Action) => ({
        open: open === action,
        onOpenChange: (isOpen: boolean) => setOpen(isOpen ? action : null),
    });
    const ids = { tenant: tenantSlug, workCenter: workCenter.id };

    return (
        <>
            {/* Not modal: the dialog an item opens takes over focus as the menu closes. */}
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Acciones de ${workCenter.name}`}
                        data-test="work-center-actions"
                        className="-my-2 -mr-2 text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-44">
                    {workCenter.archived_on === null ? (
                        <>
                            <DropdownMenuItem onSelect={() => setOpen('edit')}>
                                <Pencil />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setOpen('archive')}
                            >
                                <Archive />
                                Archivar
                            </DropdownMenuItem>
                        </>
                    ) : (
                        <DropdownMenuItem asChild>
                            <Link
                                href={restore(ids)}
                                as="button"
                                className="w-full"
                            >
                                <ArchiveRestore />
                                Restaurar
                            </Link>
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <WorkCenterDialog
                {...dialog('edit')}
                copy={{
                    title: 'Editar centro de trabajo',
                    description: `Corrige los datos de ${workCenter.name}.`,
                    submit: 'Guardar',
                }}
                form={update.form(ids)}
                states={states}
                workCenter={workCenter}
            />
            <ArchiveWorkCenterDialog
                {...dialog('archive')}
                tenantSlug={tenantSlug}
                workCenter={workCenter}
            />
        </>
    );
}
