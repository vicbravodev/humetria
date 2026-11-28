import AuditDetailBody from '@/components/tenancy/audit-detail-body';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { AuditTag } from '@/lib/audit-view';
import type { AuditEntryRow } from '@/types';

/** «Detalle del evento» in a side panel where there is no room to dock it, with the list still in view behind. */
export default function AuditDetailSheet({
    entry,
    tag,
    open,
    onOpenChange,
}: {
    entry: AuditEntryRow;
    tag: AuditTag;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[440px] sm:rounded-xl sm:border-0 sm:shadow-float">
                <SheetHeader className="gap-1 border-b px-6 pt-5 pb-4">
                    <div className="flex items-center gap-3 pr-8">
                        <SheetTitle className="flex-1 text-lg leading-[26px]">
                            Detalle del evento
                        </SheetTitle>
                        <Badge variant={tag.variant}>{tag.label}</Badge>
                    </div>
                    <SheetDescription>
                        Solo se lee: nada de aquí se edita ni se borra.
                    </SheetDescription>
                </SheetHeader>
                <div className="flex-1 overflow-y-auto">
                    <AuditDetailBody entry={entry} />
                </div>
            </SheetContent>
        </Sheet>
    );
}
