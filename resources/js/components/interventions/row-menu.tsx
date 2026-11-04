import { Ellipsis, FileText, Plus } from 'lucide-react';
import { useState } from 'react';
import ActionSheet from '@/components/interventions/action-sheet';
import AnalysisDialog from '@/components/interventions/analysis-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/campaigns/program/actions';
import type { ProgramRow, ProgramTools } from '@/types';

type Opened = 'action' | 'analysis' | null;

/** What HR does on a row: add an action to it, or write its analysis (the menu opens a side panel or a dialog). */
export default function RowMenu({
    row,
    campaignId,
    tools,
}: {
    row: ProgramRow;
    campaignId: string;
    tools: ProgramTools;
}) {
    const tenantSlug = useTenantSlug();
    const [opened, setOpened] = useState<Opened>(null);
    const close = (open: boolean) => {
        if (!open) {
            setOpened(null);
        }
    };

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Acciones de ${row.label}`}
                        className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-48">
                    <DropdownMenuItem onSelect={() => setOpened('action')}>
                        <Plus aria-hidden />
                        Agregar acción
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => setOpened('analysis')}>
                        <FileText aria-hidden />
                        Escribir el análisis
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            {opened === 'action' && (
                <ActionSheet
                    open
                    onOpenChange={close}
                    form={store.form([tenantSlug, campaignId, row.id])}
                    itemId={row.id}
                    itemLabel={row.label}
                    action={null}
                    tools={tools}
                />
            )}
            {opened === 'analysis' && (
                <AnalysisDialog
                    open
                    onOpenChange={close}
                    campaignId={campaignId}
                    row={row}
                />
            )}
        </>
    );
}
